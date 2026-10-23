<?php

declare(strict_types=1);

namespace Tapomix\Castor\Infra;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;

use function Castor\http_client;
use function Castor\io;
use function Castor\variable;

/* Compose files holding the pinned versions, in compose load order: a later file overrides an earlier one. */
define('TAPOMIX_VERSIONS_COMPOSE_FILES', ['compose.yaml', 'compose.app.yaml', 'compose.override.yaml']);

/* Days before a branch EOL from which it is announced (Symfony minors only live 8 months). */
define('TAPOMIX_VERSIONS_EOL_SOON_DAYS', 90);

/**
 * Components tracked by infra:versions.
 *
 * `depth` is the number of segments that make up a branch: PHP 8.4.25 follows 8.4, while
 * PostgreSQL 18.6 follows 18 since its second segment is already the patch.
 * The current version comes from a `*_VERSION` compose variable (`var`), from a file of the
 * application code (`file` + `pattern`) or from a package of its composer lock (`file` + `package`);
 * a component without one is not shown.
 *
 * @return list<array{label: string, depth: int, var?: string, file?: string, pattern?: string, package?: string, endoflife?: string, image?: array{0: string, 1: string}, github?: string}>
 */
function versionComponents(): array
{
    $codePath = (string) variable('APP.CODE_PATH', 'code');

    return [
        [
            'label' => 'PHP',
            'depth' => 2,
            'var' => 'PHP_VERSION',
            'endoflife' => 'php',
            // the release is only usable once the base image is published
            'image' => ['dunglas/frankenphp', 'php%s'],
        ],
        [
            'label' => 'PostgreSQL',
            'depth' => 1,
            'var' => 'PG_VERSION',
            'endoflife' => 'postgresql',
            'image' => ['library/postgres', '%s-alpine'],
        ],
        [
            'label' => 'Symfony',
            'depth' => 2,
            'file' => $codePath . '/composer.lock',
            'package' => 'symfony/framework-bundle',
            'endoflife' => 'symfony',
        ],
        [
            'label' => 'Laravel',
            'depth' => 1,
            'file' => $codePath . '/composer.lock',
            'package' => 'laravel/framework',
            'endoflife' => 'laravel',
        ],
        [
            'label' => 'Alpine.js',
            'depth' => 2,
            'file' => $codePath . '/importmap.php',
            'pattern' => importmapVersionPattern('alpinejs'),
            'github' => 'alpinejs/alpine',
        ],
        [
            'label' => 'Tailwind',
            'depth' => 2,
            'var' => 'TAILWIND_VERSION',
            // the standalone binary is downloaded from the GitHub releases
            'github' => 'tailwindlabs/tailwindcss',
        ],
        [
            'label' => 'daisyUI',
            'depth' => 2,
            // vendored bundle, loaded as a Tailwind plugin
            'file' => $codePath . '/assets/styles/daisyui.mjs',
            'pattern' => '/\bvar version = "([^"]+)"/',
            'github' => 'saadeghi/daisyui',
        ],
    ];
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_INFRA, description: 'Compare pinned versions with the latest releases', aliases: ['infra:versions'])]
function versions(
    #[AsOption(shortcut: 's', description: 'Exit with an error when an update is available, a branch is EOL or a lookup failed')]
    bool $strict = false,
): int {
    io()->title('Checking pinned versions');

    $root = PathHelper::getRoot();
    $pinned = readPinnedVersions($root);

    $rows = [];
    $actionable = false;

    foreach (versionComponents() as $component) {
        $current = readCurrentVersion($component, $pinned, $root);
        if (null === $current) {
            continue;
        }

        $branch = branchOf($current, $component['depth']);
        $lookup = lookupLatest($component, $branch, $current);

        [$status, $isActionable] = versionStatus($current, $lookup);
        $actionable = $actionable || $isActionable;

        $latest = $lookup['latest'] ?? null;
        if (null !== $latest && branchOf($latest, $component['depth']) !== $branch) {
            $latest = \sprintf('<comment>%s</comment>', $latest); // a newer branch exists
        }

        $rows[] = [
            $component['label'],
            $current,
            $branch,
            $lookup['inBranch'] ?? '?',
            $latest ?? '?',
            formatEol($lookup['eol'] ?? null),
            $status,
        ];
    }

    if ([] === $rows) {
        io()->warning('No pinned version found.');

        return Command::SUCCESS;
    }

    io()->table(['Component', 'Current', 'Branch', 'Latest in branch', 'Latest', 'Branch EOL', 'Status'], $rows);

    return $strict && $actionable ? Command::FAILURE : Command::SUCCESS;
}

/**
 * Collect the `*_VERSION` variables pinned in the compose files, overridden by the app env file.
 *
 * @return array<string, string>
 */
function readPinnedVersions(string $root): array
{
    $pinned = [];

    foreach (TAPOMIX_VERSIONS_COMPOSE_FILES as $file) {
        $path = $root . '/' . $file;
        if (\is_file($path)) {
            $pinned = [...$pinned, ...parseComposeVersionDefaults((string) \file_get_contents($path))];
        }
    }

    // the env file overrides a pin for a local test, as compose itself does
    $envFile = $root . '/' . (\defined('TAPOMIX_APP_ENV_FILE') ? TAPOMIX_APP_ENV_FILE : '.env.docker');
    if (\is_file($envFile)) {
        try {
            $env = new Dotenv()->parse((string) \file_get_contents($envFile), $envFile);
        } catch (FormatException) {
            $env = [];
        }

        foreach (\array_keys($pinned) as $var) {
            if (isset($env[$var]) && '' !== $env[$var]) {
                $pinned[$var] = $env[$var];
            }
        }
    }

    return $pinned;
}

/**
 * Extract the default value of every `${XXX_VERSION:-default}` found in a compose file.
 *
 * @return array<string, string>
 */
function parseComposeVersionDefaults(string $content): array
{
    \preg_match_all('/\$\{([A-Z0-9_]+_VERSION):-([^}\s]+)\}/', $content, $matches, \PREG_SET_ORDER);

    $defaults = [];
    foreach ($matches as [, $var, $default]) {
        $defaults[$var] = $default;
    }

    return $defaults;
}

/**
 * @param array{var?: string, file?: string, pattern?: string, package?: string} $component
 * @param array<string, string>                                                  $pinned
 */
function readCurrentVersion(array $component, array $pinned, string $root): ?string
{
    if (isset($component['var'])) {
        return $pinned[$component['var']] ?? null;
    }

    if (!isset($component['file'])) {
        return null;
    }

    $path = $root . '/' . $component['file'];
    if (!\is_file($path)) {
        return null;
    }

    $content = (string) \file_get_contents($path);

    if (isset($component['package'])) {
        return parseLockedVersion($content, $component['package']);
    }

    if (isset($component['pattern'])) {
        return 1 === \preg_match($component['pattern'], $content, $matches) ? $matches[1] : null;
    }

    return null;
}

/** Pattern capturing the version pinned for a package in a Symfony AssetMapper importmap, whatever the order of its keys. */
function importmapVersionPattern(string $package): string
{
    return \sprintf("/'%s'\\s*=>\\s*\\[[^\\]]*'version'\\s*=>\\s*'([^']+)'/", \preg_quote($package, '/'));
}

/** Installed version of a package in a composer lock, without its tag prefix. */
function parseLockedVersion(string $lock, string $package): ?string
{
    /** @var array{packages?: list<array{name: string, version: string}>}|null $data */
    $data = \json_decode($lock, true);

    foreach ($data['packages'] ?? [] as $locked) {
        if ($locked['name'] === $package) {
            return normalizeVersion($locked['version']);
        }
    }

    return null;
}

/**
 * @param array{label: string, depth: int, var?: string, file?: string, pattern?: string, package?: string, endoflife?: string, image?: array{0: string, 1: string}, github?: string} $component
 *
 * @return ?array{inBranch: ?string, latest: ?string, eol: ?string, pending: bool} null when the lookup failed
 */
function lookupLatest(array $component, string $branch, string $current): ?array
{
    try {
        if (isset($component['endoflife'])) {
            $lookup = lookupEndOfLife($component['endoflife'], $branch);

            // a release without its base image cannot be built yet
            if (isset($component['image']) && null !== $lookup['inBranch'] && isNewerVersion($lookup['inBranch'], $current)) {
                [$image, $tagFormat] = $component['image'];
                $lookup['pending'] = !dockerTagExists($image, \sprintf($tagFormat, $lookup['inBranch']));
            }

            return $lookup;
        }

        if (isset($component['github'])) {
            return lookupGithubReleases($component['github'], $branch, $component['depth']);
        }
    } catch (HttpExceptionInterface $httpException) {
        io()->warning(\sprintf('%s: lookup failed (%s)', $component['label'], $httpException->getMessage()));
    }

    return null;
}

/** @return array{inBranch: ?string, latest: ?string, eol: ?string, pending: bool} */
function lookupEndOfLife(string $product, string $branch): array
{
    /** @var list<array{cycle: string, latest: string, eol: bool|string}> $cycles */
    $cycles = httpGetJson(\sprintf('https://endoflife.date/api/%s.json', $product));

    $lookup = ['inBranch' => null, 'latest' => null, 'eol' => null, 'pending' => false];

    foreach ($cycles as $cycle) {
        if (null === $lookup['latest'] || isNewerVersion($cycle['latest'], $lookup['latest'])) {
            $lookup['latest'] = $cycle['latest'];
        }

        if ((string) $cycle['cycle'] === $branch) {
            $lookup['inBranch'] = $cycle['latest'];
            $lookup['eol'] = \is_string($cycle['eol']) ? $cycle['eol'] : null;
        }
    }

    return $lookup;
}

/** @return array{inBranch: ?string, latest: ?string, eol: ?string, pending: bool} */
function lookupGithubReleases(string $repository, string $branch, int $depth): array
{
    /** @var list<array{tag_name: string, draft: bool, prerelease: bool}> $releases */
    $releases = httpGetJson(\sprintf('https://api.github.com/repos/%s/releases?per_page=100', $repository));

    $tags = \array_column(
        \array_filter($releases, fn (array $release): bool => !$release['draft'] && !$release['prerelease']),
        'tag_name',
    );

    return [
        'inBranch' => latestVersion($tags, $branch, $depth),
        'latest' => latestVersion($tags),
        'eol' => null,
        'pending' => false,
    ];
}

function dockerTagExists(string $image, string $tag): bool
{
    $url = \sprintf('https://hub.docker.com/v2/repositories/%s/tags/%s', $image, $tag);

    return 200 === http_client()->request('GET', $url, ['timeout' => 10])->getStatusCode();
}

/** @return array<mixed> */
function httpGetJson(string $url): array
{
    $headers = ['User-Agent' => 'tapomix-castor-tasks']; // required by the GitHub API

    // unauthenticated GitHub calls are limited to 60 per hour and per IP
    if (\str_starts_with($url, 'https://api.github.com/') && isset($_SERVER['GITHUB_TOKEN'])) {
        $headers['Authorization'] = 'Bearer ' . $_SERVER['GITHUB_TOKEN'];
    }

    return http_client()->request('GET', $url, ['headers' => $headers, 'timeout' => 10])->toArray();
}

/**
 * @param ?array{inBranch: ?string, latest: ?string, eol: ?string, pending: bool} $lookup
 *
 * @return array{0: string, 1: bool} the status label and whether it calls for an action
 */
function versionStatus(string $current, ?array $lookup): array
{
    if (null === $lookup || null === $lookup['inBranch']) {
        return ['<error>lookup failed</error>', true];
    }

    $eolState = eolState($lookup['eol']);

    if ('past' === $eolState) {
        return ['<error>branch EOL</error>', true];
    }

    if (isNewerVersion($lookup['inBranch'], $current)) {
        return $lookup['pending']
            ? ['<comment>update pending (no image yet)</comment>', false]
            : ['<comment>update available</comment>', true];
    }

    if ('soon' === $eolState) {
        return ['<comment>EOL soon</comment>', false];
    }

    return ['<info>up to date</info>', false];
}

/**
 * Whether an EOL date is already past, close enough to be announced, or neither.
 *
 * @return 'past'|'soon'|null
 */
function eolState(?string $eol): ?string
{
    if (null === $eol) {
        return null;
    }

    if ($eol < \date('Y-m-d')) {
        return 'past';
    }

    return $eol <= \date('Y-m-d', \strtotime(\sprintf('+%d days', TAPOMIX_VERSIONS_EOL_SOON_DAYS))) ? 'soon' : null;
}

/** EOL date, highlighted once it is past or close. */
function formatEol(?string $eol): string
{
    return match (eolState($eol)) {
        'past' => \sprintf('<error>%s</error>', $eol),
        'soon' => \sprintf('<comment>%s</comment>', $eol),
        default => $eol ?? '—',
    };
}

/** Strip the tag prefix ("v4.3.3" => "4.3.3"). */
function normalizeVersion(string $version): string
{
    return \ltrim($version, 'vV');
}

/** Keep the first $depth segments of a version ("8.4.25", 2 => "8.4"). */
function branchOf(string $version, int $depth): string
{
    return \implode('.', \array_slice(\explode('.', normalizeVersion($version)), 0, $depth));
}

function isNewerVersion(string $candidate, string $reference): bool
{
    return \version_compare(normalizeVersion($candidate), normalizeVersion($reference), '>');
}

/**
 * Highest stable version, optionally restricted to a branch, returned as given (tag prefix kept).
 *
 * @param string[] $versions
 */
function latestVersion(array $versions, ?string $branch = null, int $depth = 0): ?string
{
    $latest = null;

    foreach ($versions as $version) {
        if (1 !== \preg_match('/^v?\d+(\.\d+)*$/i', $version)) {
            continue; // pre-releases and odd tags
        }

        if (null !== $branch && branchOf($version, $depth) !== $branch) {
            continue;
        }

        if (null === $latest || isNewerVersion($version, $latest)) {
            $latest = $version;
        }
    }

    return $latest;
}
