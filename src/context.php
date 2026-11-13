<?php

declare(strict_types=1);

namespace Tapomix\Castor;

use Castor\Attribute\AsContext;
use Castor\Context;
use Castor\Helper\PathHelper;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

/* The app env file, fixed: Compose reads `.env` by itself, castor must read the same one. */
define('TAPOMIX_DOTENV_FILE', '.env');
define('TAPOMIX_CASTOR_ENV_FILE', '.castor/.env.castor');
define('TAPOMIX_DEFAULT_CONTEXT', 'tapomix-default');

define('EXPR_FRAMEWORK_SYMFONY', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.FRAMEWORK'] === 'symfony'");
define('EXPR_FRAMEWORK_LARAVEL', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.FRAMEWORK'] === 'laravel'");

define('EXPR_ENV_DEV', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.ENVIRONMENT'] === 'dev'");
define('EXPR_ENV_PROD', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.ENVIRONMENT'] === 'prod'");

#[AsContext(name: TAPOMIX_DEFAULT_CONTEXT)] // don't defined as default to allow override
function default_context(): Context
{
    loadEnvFiles(PathHelper::getRoot());

    $data = [
        // from castor specific env vars
        'APP.CODE_PATH' => $_SERVER['APP_CODE_PATH'] ?? 'code',
        'APP.FRAMEWORK' => $_SERVER['APP_FRAMEWORK'] ?? 'vanilla',

        'CASTOR.DEFAULT_BROWSER' => $_SERVER['CASTOR_DEFAULT_BROWSER'] ?? 'firefox-developer-edition',

        'INFRA.REMOTE' => $_SERVER['INFRA_REMOTE'] ?? 'infra',

        'DOCKER.SERVICES.DB' => $_SERVER['APP_SERVICE_DB'] ?? 'db',
        'DOCKER.SERVICES.NODE' => $_SERVER['APP_SERVICE_NODE'] ?? 'node',
        'DOCKER.SERVICES.PHP' => $_SERVER['APP_SERVICE_PHP'] ?? 'php',

        ...appContextData($_SERVER),
    ];

    return new Context(
        data: $data,
        tty: hasControllingTty(),
    );
}

/**
 * Load `.env` as Compose reads it, then the castor file.
 *
 * Plain Dotenv::load(), not castor's load_dot_env() (Dotenv::loadEnv()): that one defaults APP_ENV to `dev` and stacks
 * `.env.local` / `.env.<env>`, so castor would run a dev config where Compose fails on a missing APP_ENV. The castor
 * file only adds variables: it may interpolate the `.env` ones, never replace them. An exported variable wins over
 * both, as for Compose. Plain is_file(): usable without a booted castor container.
 */
function loadEnvFiles(string $root): void
{
    $dotenv = new Dotenv();

    foreach ([TAPOMIX_DOTENV_FILE, TAPOMIX_CASTOR_ENV_FILE] as $file) {
        if (\is_file($root . '/' . $file)) {
            $dotenv->load($root . '/' . $file);
        }

        // forget which variables Dotenv loaded: the next file sees them as pre-existing, never overridden
        unset($_SERVER['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_VARS']);
    }
}

/**
 * App variables, read from the infra names of the app env file only: castor and Compose read the same values.
 * The compose variables come from the environment first, as Compose itself does.
 *
 * @param array<string, mixed> $env
 *
 * @return array<string, mixed>
 */
function appContextData(array $env): array
{
    return [
        'APP.DB.NAME' => $env['DB_NAME'] ?? 'db',
        'APP.DB.USER' => $env['DB_USER'] ?? 'user',
        'APP.ENVIRONMENT' => $env['APP_ENV'] ?? 'dev',
        'APP.NAME' => $env['CONTAINER_NAME'] ?? 'app',
        'APP.SERVER_NAME' => $env['TRAEFIK_HOST'] ?? 'localhost',

        'COMPOSE.FILE' => $env['COMPOSE_FILE'] ?? '',
        'COMPOSE.PROFILES' => $env['COMPOSE_PROFILES'] ?? '',
    ];
}

/**
 * A supported TTY is not enough: Symfony literally opens `/dev/tty` when TTY mode is enabled.
 *
 * A castor spawned by another castor (`qa:all` starts one child per analyzer) runs behind a
 * PTY, so its stdout *is* a TTY and `Process::isTtySupported()` returns true — but it inherits
 * no controlling terminal when the top-level castor had none. Opening `/dev/tty` then fails
 * and every child dies before starting.
 *
 * Invisible from a terminal, where `/dev/tty` exists. It only bites callers that have none:
 * git hooks, CI, systemd timers, and AI coding agents shelling out to a castor task.
 */
function hasControllingTty(): bool
{
    if (!Process::isTtySupported()) {
        return false;
    }

    $tty = @fopen('/dev/tty', 'r');

    if (false === $tty) {
        return false;
    }

    fclose($tty);

    return true;
}
