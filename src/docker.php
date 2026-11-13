<?php

declare(strict_types=1);

namespace Tapomix\Castor\Docker;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Console\Output\VerbosityLevel;
use Castor\Context;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\io;
use function Castor\run as castor_run;
use function Castor\variable;

define('TAPOMIX_NAMESPACE_DOCKER', 'tapomix-docker');

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Build all services', aliases: ['build'])]
function build(bool $noCache = false, bool $noPull = false): void
{
    io()->title('Building server');

    $context = context()->withVerbosityLevel(VerbosityLevel::VERBOSE);

    $cmd = buildBaseDockerComposeCmd();
    $cmd[] = 'build';
    if ($noCache) {
        $cmd[] = '--no-cache';
    }

    // pull by default: a cached base image is never refreshed otherwise, even when its tag was republished
    if (!$noPull) {
        $cmd[] = '--pull';
    }

    castor_run($cmd, context: composeContext($context));
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Pull fresh images', aliases: ['pull'])]
function pull(): void
{
    io()->title('Pulling images');

    castor_run([...buildBaseDockerComposeCmd(), 'pull', '--ignore-buildable'], context: composeContext());
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Start all services', aliases: ['start', 'up'])]
function start(
    #[AsOption(shortcut: 'u', description: 'Pull and build the images first')]
    bool $update = false,
): void {
    // up only recreates the containers whose image changed
    if ($update) {
        pull();
        build();
    }

    io()->title('Starting server');

    castor_run([...buildBaseDockerComposeCmd(), 'up', '--detach', '--wait'], context: composeContext());
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Stop all services', aliases: ['stop', 'down'])]
function stop(): void
{
    io()->title('Stopping server');

    castor_run([...buildBaseDockerComposeCmd(), 'down', '--remove-orphans'], context: composeContext());
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Show server logs', aliases: ['logs'])]
function logs(): void
{
    io()->title('Showing server logs');

    // -f: follow the log output, -t: show timestamps
    castor_run([...buildBaseDockerComposeCmd(), 'logs', '-f', '-t'], context: composeContext());
}

/** Note: Alias is 'shell' (not 'sh') to avoid confusion since the default shell is 'bash', not 'sh' */
#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Open terminal in a container', aliases: ['shell'])]
function shell(
    string $service,
    #[AsOption(description: 'Shell to use in the container')]
    string $shell = 'bash',
): void {
    exec($service, [$shell]);
}

/**
 * Plain `docker compose`: the project `.env` picks the compose files (COMPOSE_FILE) and the profiles, so a hand-typed
 * `docker compose` sees the same config. Validated first: Compose silently ignores a file left out of COMPOSE_FILE.
 * Run it through composeContext(): Compose reads `.env` and resolves COMPOSE_FILE from its working directory.
 *
 * @return string[]
 */
function buildBaseDockerComposeCmd(): array
{
    validateComposeFile(
        (string) variable('COMPOSE.FILE'),
        (string) variable('COMPOSE.PROFILES'),
        (string) variable('APP.ENVIRONMENT'),
        \defined('TAPOMIX_PROJECT_SITE'), // defined by the php preset
    );

    return ['docker', 'compose'];
}

/** The project root as working directory, whatever the shell one: the only way Compose finds `.env` from a subdirectory. */
function composeContext(?Context $context = null): Context
{
    return ($context ?? context())->withWorkingDirectory(PathHelper::getRoot());
}

/** The compose files of a site, in this order, in every environment: base, environment, app, machine-local override. */
function siteComposeFile(string $appEnv): string
{
    return \sprintf('compose.yaml:compose.%s.yaml:compose.app.yaml:compose.override.yaml', $appEnv);
}

/**
 * COMPOSE_FILE must be set in every project; a site must load exactly its four levels, with the profile of its env.
 * A service lists its own files, never checked: they vary (Traefik leaves compose.sites.yaml out in dev).
 */
function validateComposeFile(string $composeFile, string $composeProfiles, string $appEnv, bool $site): void
{
    if (!$site) {
        if ('' === $composeFile) {
            throw new ProblemException('COMPOSE_FILE is not set (environment or .env): docker compose would load compose.yaml alone.');
        }

        return;
    }

    $expected = siteComposeFile($appEnv);

    if ($composeFile !== $expected) {
        throw new ProblemException(\sprintf('COMPOSE_FILE is "%s" (environment or .env), expected "%s" (APP_ENV=%s).', $composeFile, $expected, $appEnv));
    }

    if ($composeProfiles !== $appEnv) {
        throw new ProblemException(\sprintf('COMPOSE_PROFILES is "%s" (environment or .env), expected "%s" (APP_ENV).', $composeProfiles, $appEnv));
    }
}

/** @param string[] $command */
function run(string $service, array $command, ?Context $context = null): Process
{
    return castor_run([...buildBaseDockerComposeCmd(), 'run', '--rm', $service, ...$command], context: composeContext($context));
}

/**
 * @param string[]              $command
 * @param array<string, string> $env     extra variables, passed as `-e KEY=VALUE` to `docker compose exec`
 */
function exec(string $service, array $command, ?Context $context = null, array $env = []): Process
{
    $envArgs = [];
    foreach ($env as $key => $value) {
        $envArgs = [...$envArgs, '-e', \sprintf('%s=%s', $key, $value)];
    }

    return castor_run([...buildBaseDockerComposeCmd(), 'exec', ...$envArgs, $service, ...$command], context: composeContext($context));
}
