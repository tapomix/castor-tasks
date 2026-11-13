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

// TAPOMIX_NAMESPACE_VPS and ensureComposerAuthFile(); require_once, like castor import(), so loaded once
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
    $rows = [];
    $problems = [];

    // 1. the infra .env, prefilled from the VPS layout
    $dotenvPath = $root . '/' . TAPOMIX_DOTENV_FILE;
    if (!\is_file($dotenvPath)) {
        $values = [...envFromPath($root), 'DB_PASS' => generateToken()];
        \file_put_contents($dotenvPath, prefillDotenv(readDist($root . '/.env.dist'), $values));
        $rows[] = ['.env', \sprintf('created: CONTAINER_NAME=%s, APP_ENV=prod, TRAEFIK_HOST=%s, DB_PASS generated', $values['CONTAINER_NAME'], $values['TRAEFIK_HOST'])];
    }

    $env = parseDotenv($dotenvPath);
    $envProblems = dotenvProblems($env);
    if (!\array_key_exists(0, $rows)) {
        $rows[] = ['.env', [] === $envProblems ? '✔' : '✘ ' . \implode(', ', $envProblems)];
    }

    $problems = [...$problems, ...$envProblems];

    // 2. the castor .env, framework detected from the code
    $castorEnvPath = $root . '/' . TAPOMIX_CASTOR_ENV_FILE;
    if (!\is_file($castorEnvPath)) {
        $dist = readDist($castorEnvPath . '.dist');
        $codeDir = $root . '/' . ((new Dotenv()->parse($dist)['APP_CODE_PATH'] ?? '') ?: 'code');
        $framework = detectFramework($codeDir);
        \file_put_contents($castorEnvPath, prefillDotenv($dist, ['APP_FRAMEWORK' => $framework]));
        $rows[] = ['.castor/.env.castor', \sprintf('created: APP_FRAMEWORK=%s (detected)', $framework)];
    } else {
        $rows[] = ['.castor/.env.castor', '✔'];
    }

    $castorEnv = parseDotenv($castorEnvPath);
    $codePath = ($castorEnv['APP_CODE_PATH'] ?? '') ?: 'code';

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
    if ('symfony' === ($castorEnv['APP_FRAMEWORK'] ?? '')) {
        $prodLocalPath = $root . '/' . $codePath . '/.env.prod.local';
        $prodLocal = $codePath . '/.env.prod.local';
        if (!\is_dir(\dirname($prodLocalPath))) {
            $rows[] = [$prodLocal, '✘ ' . $codePath . '/ missing: git submodule update --init'];
            $problems[] = $codePath . '/ missing';
        } elseif (!\is_file($prodLocalPath)) {
            \file_put_contents($prodLocalPath, 'APP_SECRET=' . generateToken() . "\n");
            $rows[] = [$prodLocal, 'created: APP_SECRET generated — add the project prod values by hand'];
        } elseif (!hasAppSecret((string) \file_get_contents($prodLocalPath))) {
            $rows[] = [$prodLocal, '✘ APP_SECRET missing or empty'];
            $problems[] = $prodLocal . ': APP_SECRET missing or empty';
        } else {
            $rows[] = [$prodLocal, '✔'];
        }
    }

    // 5. the compose files, as the docker tasks will check them
    try {
        validateComposeFile($env['COMPOSE_FILE'] ?? '', $env['COMPOSE_PROFILES'] ?? '', $env['APP_ENV'] ?? '', true);
        $rows[] = ['COMPOSE_FILE', '✔'];
    } catch (ProblemException $problemException) {
        $rows[] = ['COMPOSE_FILE', '✘ ' . $problemException->getMessage()];
        $problems[] = $problemException->getMessage();
    }

    // 6. the site network, declared on the Traefik side
    $containerName = $env['CONTAINER_NAME'] ?? '';
    if ('' !== $containerName) {
        $network = 'traefik-' . $containerName;
        $inspect = run(['docker', 'network', 'inspect', $network], context()->withQuiet()->withAllowFailure());
        if ($inspect->isSuccessful()) {
            $rows[] = ['network ' . $network, '✔'];
        } else {
            $rows[] = ['network ' . $network, \sprintf('✘ missing: castor traefik:site:add %s [--puppeteer] in Traefik, push, castor service:update on the VPS', $containerName)];
            $problems[] = 'network ' . $network . ' missing';
        }
    }

    io()->table(['Item', 'State'], $rows);

    if ([] !== $problems) {
        io()->error(['Fix these, then run castor vps:init again:', ...$problems]);

        return 1;
    }

    io()->success('Site ready. Next step: castor vps:deploy');

    return 0;
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
