<?php

declare(strict_types=1);

namespace Tapomix\Castor\Vps;

use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Tapomix\Castor\Docker\validateComposeFile;
use function Tapomix\Castor\Tools\generateToken;

// TAPOMIX_NAMESPACE_VPS, ensureComposerAuthFile() and writeSecretFile(); require_once, like castor import(), so loaded once
require_once __DIR__ . '/deploy.php';

/* The machine-local Compose layer, written when absent: COMPOSE_FILE lists it, Compose fails on a missing file. */
const OVERRIDE_CONTENT = <<<'YAML'
    # Machine-local Docker Compose layer — gitignored, loaded last (COMPOSE_FILE in .env).
    #
    # ! keep the file even when empty: COMPOSE_FILE lists it, a missing one aborts docker compose.
    #
    # Only what must not leave this machine belongs here; anything committable goes in compose.app.yaml.

    YAML;

/**
 * Bring a freshly cloned site to a deployable state: create what is missing, never overwrite, report every item.
 *
 * Run it again until everything is ✔: the output is the to-do list. Enabled while APP_ENV is unset (a fresh clone, no
 * .env yet) or prod; hidden on a dev machine.
 */
#[AsTask(namespace: TAPOMIX_NAMESPACE_VPS, description: 'Initialize a freshly cloned site on the VPS', aliases: ['vps:init'], enabled: EXPR_ENV_UNSET_OR_PROD)]
function init(): int
{
    io()->title('Initializing the site');

    $root = PathHelper::getRoot();
    [$uid, $gid] = currentUser();
    $rows = [];
    $problems = [];

    // 1. the infra .env, prefilled from the VPS layout and the user running castor
    $dotenvPath = $root . '/' . TAPOMIX_DOTENV_FILE;
    $created = null;
    if (!\is_file($dotenvPath)) {
        try {
            $values = [...envFromPath($root), 'DB_PASS' => generateToken(), 'USER_ID' => (string) $uid, 'GROUP_ID' => (string) $gid];
            writeSecretFile($dotenvPath, prefillDotenv(readDist($root . '/.env.dist'), $values));
            $created = \sprintf('created: CONTAINER_NAME=%s, APP_ENV=prod, TRAEFIK_HOST=%s, USER_ID:GROUP_ID=%d:%d, DB_PASS generated', $values['CONTAINER_NAME'], $values['TRAEFIK_HOST'], $uid, $gid);
        } catch (ProblemException $problemException) {
            $rows[] = ['.env', '✘ ' . $problemException->getMessage()];
            $problems[] = $problemException->getMessage();
        }
    }

    $env = \is_file($dotenvPath) ? parseDotenv($dotenvPath) : [];
    if (\is_file($dotenvPath)) {
        $envProblems = [...dotenvProblems($env), ...userProblems($env, $uid, $gid)];
        $state = [] === $envProblems ? '✔' : '✘ ' . \implode(', ', $envProblems);
        $rows[] = ['.env', null === $created ? $state : $created . ([] === $envProblems ? '' : ' — ' . $state)];
        $problems = [...$problems, ...$envProblems];
    }

    // 2. the code, then the castor .env with the framework detected from it
    $castorEnvPath = $root . '/' . TAPOMIX_CASTOR_ENV_FILE;
    $castorEnvExists = \is_file($castorEnvPath);
    $castorSource = null;
    try {
        $castorSource = $castorEnvExists ? (string) \file_get_contents($castorEnvPath) : readDist($castorEnvPath . '.dist');
    } catch (ProblemException $problemException) {
        $rows[] = ['.castor/.env.castor', '✘ ' . $problemException->getMessage()];
        $problems[] = $problemException->getMessage();
    }

    $codePath = ((new Dotenv()->parse($castorSource ?? '')['APP_CODE_PATH'] ?? '') ?: 'code');
    $codeReady = codeCheckedOut($root . '/' . $codePath);

    if (!$codeReady) {
        // an uninitialized submodule leaves an empty directory: nothing to detect, nothing to write in it
        $rows[] = [$codePath . '/', '✘ not checked out: git submodule update --init'];
        $problems[] = $codePath . '/ not checked out';
    }

    if (null !== $castorSource) {
        if ($castorEnvExists) {
            $rows[] = ['.castor/.env.castor', '✔'];
        } elseif ($codeReady) {
            $framework = detectFramework($root . '/' . $codePath);
            \file_put_contents($castorEnvPath, prefillDotenv($castorSource, ['APP_FRAMEWORK' => $framework]));
            $rows[] = ['.castor/.env.castor', \sprintf('created: APP_FRAMEWORK=%s (detected)', $framework)];
        } else {
            $rows[] = ['.castor/.env.castor', '✘ waits for ' . $codePath . '/: the framework is detected from it'];
        }
    }

    $castorEnv = \is_file($castorEnvPath) ? parseDotenv($castorEnvPath) : [];

    // 3. the local files git does not carry
    $overridePath = $root . '/compose.override.yaml';
    $rows[] = ['compose.override.yaml', \is_file($overridePath) ? '✔' : 'created (comments only)'];
    if (!\is_file($overridePath)) {
        \file_put_contents($overridePath, OVERRIDE_CONTENT);
    }

    $authExisted = \is_file($root . '/.docker/.composer-auth.json');
    ensureComposerAuthFile($root);
    $rows[] = ['.docker/.composer-auth.json', $authExisted ? '✔' : 'created ({})'];

    // 4. the Symfony secret: the code env file, distinct from the infra .env
    if ($codeReady && 'symfony' === ($castorEnv['APP_FRAMEWORK'] ?? '')) {
        $prodLocalPath = $root . '/' . $codePath . '/.env.prod.local';
        $prodLocal = $codePath . '/.env.prod.local';
        if (!\is_file($prodLocalPath)) {
            writeSecretFile($prodLocalPath, 'APP_SECRET=' . generateToken() . "\n");
            $rows[] = [$prodLocal, 'created: APP_SECRET generated — add the project prod values by hand'];
        } elseif (!hasAppSecret((string) \file_get_contents($prodLocalPath))) {
            $rows[] = [$prodLocal, '✘ APP_SECRET missing or empty'];
            $problems[] = $prodLocal . ': APP_SECRET missing or empty';
        } else {
            $rows[] = [$prodLocal, '✔'];
        }
    }

    // 5. the compose files, as the docker tasks will check them — an unset APP_ENV is already reported above
    if ('' === ($env['APP_ENV'] ?? '')) {
        $rows[] = ['COMPOSE_FILE', '✘ waits for APP_ENV'];
    } else {
        try {
            validateComposeFile($env['COMPOSE_FILE'] ?? '', $env['COMPOSE_PROFILES'] ?? '', $env['APP_ENV'], true);
            $rows[] = ['COMPOSE_FILE', '✔'];
        } catch (ProblemException $problemException) {
            $rows[] = ['COMPOSE_FILE', '✘ ' . $problemException->getMessage()];
            $problems[] = $problemException->getMessage();
        }
    }

    // 6. the site network, declared on the Traefik side
    $containerName = $env['CONTAINER_NAME'] ?? '';
    if ('' !== $containerName) {
        $network = 'traefik-' . $containerName;
        $inspect = run(['docker', 'network', 'inspect', $network], context()->withQuiet()->withAllowFailure());
        switch (networkState($inspect->isSuccessful(), $inspect->getErrorOutput())) {
            case 'present':
                $rows[] = ['network ' . $network, '✔'];
                break;
            case 'missing':
                $rows[] = ['network ' . $network, \sprintf('✘ missing: castor traefik:site:add %s [--puppeteer] in Traefik, push, castor service:update on the VPS', $containerName)];
                $problems[] = 'network ' . $network . ' missing';
                break;
            default:
                $error = \strtok(\trim($inspect->getErrorOutput()), "\n") ?: 'no output';
                $rows[] = ['network ' . $network, '✘ docker unavailable: ' . $error];
                $problems[] = 'docker unavailable (daemon, or the user not in the docker group)';
        }
    }

    io()->table(['Item', 'State'], $rows);

    if ([] !== $problems) {
        io()->error(['Fix these, then run castor vps:init again:', ...\array_values(\array_unique($problems))]);

        return 1;
    }

    io()->success('Site ready. Next step: castor vps:deploy');

    return 0;
}

/**
 * The user running castor: the container runs as USER_ID:GROUP_ID and must own the files of the bind mounts.
 *
 * @return array{int, int}
 */
function currentUser(): array
{
    if (\function_exists('posix_getuid') && \function_exists('posix_getgid')) {
        return [\posix_getuid(), \posix_getgid()];
    }

    $id = static fn (string $flag): int => (int) run(['id', $flag], context()->withQuiet())->getOutput();

    return [$id('-u'), $id('-g')];
}

/**
 * USER_ID / GROUP_ID against the user running castor on the VPS.
 *
 * @param array<string, string> $env
 *
 * @return list<string>
 */
function userProblems(array $env, int $uid, int $gid): array
{
    $problems = [];

    foreach (['USER_ID' => $uid, 'GROUP_ID' => $gid] as $key => $expected) {
        $value = $env[$key] ?? '';
        if ('' === $value) {
            $problems[] = $key . ' is not set';
        } elseif ((string) $expected !== $value) {
            $problems[] = \sprintf('%s is "%s", expected "%d" (the user running castor)', $key, $value, $expected);
        }
    }

    return $problems;
}

/** `present`, `missing` or `docker-unavailable`: a failing inspect is not always a missing network. */
function networkState(bool $successful, string $errorOutput): string
{
    if ($successful) {
        return 'present';
    }

    return \str_contains($errorOutput, 'not found') || \str_contains($errorOutput, 'No such network') ? 'missing' : 'docker-unavailable';
}

/** A `.dist` file to copy: its absence is a broken checkout, not something to work around. */
function readDist(string $path): string
{
    if (!\is_file($path)) {
        throw new ProblemException(\sprintf('"%s" not found: is this a site checkout?', $path));
    }

    return (string) \file_get_contents($path);
}

/** @return array<string, string> */
function parseDotenv(string $path): array
{
    try {
        return new Dotenv()->parse((string) \file_get_contents($path), $path);
    } catch (FormatException $formatException) {
        throw new ProblemException($formatException->getMessage(), $formatException->getCode(), previous: $formatException);
    }
}

/**
 * Set the given keys in a dotenv content: the first uncommented `KEY=` line of each key becomes `KEY=value` (its inline
 * comment dropped, it described the placeholder), a key absent from the content is appended.
 *
 * @param array<string, string> $values
 */
function prefillDotenv(string $content, array $values): string
{
    foreach ($values as $key => $value) {
        $pattern = '/^' . \preg_quote($key, '/') . '=.*$/m';
        $line = $key . '=' . $value;

        if (1 === \preg_match($pattern, $content)) {
            // a callback: the value may hold `$` (an interpolation), never read as a backreference
            $content = (string) \preg_replace_callback($pattern, static fn (): string => $line, $content, 1);

            continue;
        }

        $content = \rtrim($content, "\n") . "\n" . $line . "\n";
    }

    return $content;
}

/**
 * The `.env` values the VPS layout gives: sites live in `<web>/<domain>/<site>`, served at `https://<site>.<domain>`.
 *
 * @return array<string, string>
 */
function envFromPath(string $projectDir): array
{
    $projectDir = \rtrim($projectDir, '/');

    return [
        'CONTAINER_NAME' => \basename($projectDir),
        'APP_ENV' => 'prod',
        'TRAEFIK_HOST' => '${CONTAINER_NAME}.' . \basename(\dirname($projectDir)),
    ];
}

/** The code is there: an uninitialized submodule leaves its directory empty. */
function codeCheckedOut(string $codeDir): bool
{
    return \is_dir($codeDir) && [] !== \array_diff((array) \scandir($codeDir), ['.', '..']);
}

function detectFramework(string $codeDir): string
{
    return match (true) {
        \is_file($codeDir . '/bin/console') => 'symfony',
        \is_file($codeDir . '/artisan') => 'laravel',
        default => 'vanilla',
    };
}

/**
 * What still needs the user in the infra `.env` of a VPS site.
 *
 * @param array<string, string> $env
 *
 * @return list<string>
 */
function dotenvProblems(array $env): array
{
    $problems = [];

    if ('' === ($env['CONTAINER_NAME'] ?? '')) {
        $problems[] = 'CONTAINER_NAME is empty';
    }

    $appEnv = $env['APP_ENV'] ?? '';
    if ('' === $appEnv) {
        $problems[] = 'APP_ENV is not set';
    } elseif ('prod' !== $appEnv) {
        $problems[] = \sprintf('APP_ENV is "%s", expected "prod"', $appEnv);
    }

    // the parsed value: TRAEFIK_HOST=${CONTAINER_NAME}.<domain>, the domain missing or still the .env.dist placeholder
    $host = $env['TRAEFIK_HOST'] ?? '';
    if ('' === $host) {
        $problems[] = 'TRAEFIK_HOST is not set';
    } elseif (\str_ends_with($host, '.') || \str_contains($host, 'domain.ext')) {
        $problems[] = \sprintf('TRAEFIK_HOST is "%s", expected <site>.<domain>', $host);
    }

    if (\in_array($env['DB_PASS'] ?? '', ['', 'ChangeMe!'], true)) {
        $problems[] = 'DB_PASS is still the .env.dist default';
    }

    return $problems;
}

/** `APP_SECRET` set and not empty in a Symfony env file; its other values are project-specific, never inspected. */
function hasAppSecret(string $content): bool
{
    try {
        $env = new Dotenv()->parse($content);
    } catch (FormatException) {
        return false;
    }

    return '' !== ($env['APP_SECRET'] ?? '');
}
