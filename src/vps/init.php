<?php

declare(strict_types=1);

namespace Tapomix\Castor\Vps;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

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
