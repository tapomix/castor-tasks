<?php

declare(strict_types=1);

namespace Tapomix\Castor\Service;

use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;
use Symfony\Component\Dotenv\Dotenv;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

define('TAPOMIX_NAMESPACE_SERVICE', 'tapomix-service');

/**
 * Plain `docker compose`: the service `.env` picks the compose files through COMPOSE_FILE.
 */
#[AsTask(namespace: TAPOMIX_NAMESPACE_SERVICE, description: 'Update and restart a Docker service', aliases: ['service:update'])]
function update(): void
{
    io()->title('Updating the service');

    $dotEnvPath = PathHelper::getRoot() . '/.env';
    $dotEnv = fs()->exists($dotEnvPath) ? (string) \file_get_contents($dotEnvPath) : '';

    // without it, docker compose silently loads compose.yaml alone: the service would run a partial config
    if (!isComposeFileDefined($_SERVER, $dotEnv)) {
        throw new ProblemException('COMPOSE_FILE is not set in the service .env: docker compose would load compose.yaml alone.');
    }

    // read the lock before the pull, to detect the one the pull is about to bring
    $castorLockPath = PathHelper::getRoot() . '/.castor/castor.composer.lock';
    $castorLockBefore = hashFile($castorLockPath);

    io()->section('Pulling latest code');
    run(['git', 'pull']);

    if ($castorLockBefore !== hashFile($castorLockPath)) {
        updateCastorTasks();
    }

    io()->section('Pulling images');
    run(['docker', 'compose', 'pull', '--ignore-buildable']);

    io()->section('Building images');
    run(['docker', 'compose', 'build', '--pull']);

    io()->section('Restarting containers');
    // always recreate: Compose does not watch the mounted config files, a plain `up` would keep the old config.
    // the short cut is accepted
    run(['docker', 'compose', 'up', '--detach', '--wait', '--force-recreate']);

    io()->success('Service updated.');
}

/**
 * COMPOSE_FILE as docker compose sees it: from the environment, or from the `.env` of the project directory.
 *
 * @param array<string, mixed> $env
 */
function isComposeFileDefined(array $env, string $dotEnvContent): bool
{
    $value = $env['COMPOSE_FILE'] ?? new Dotenv()->parse($dotEnvContent)['COMPOSE_FILE'] ?? '';

    return '' !== $value;
}

/**
 * Install the castor tasks the pull brought, as castor will not act on a new lock by itself.
 *
 * Castor reinstalls its own dependencies when the lock "content-hash" stops matching
 * .castor/vendor/composer.installed, and that hash covers castor.composer.json alone: a lock bumped
 * under the same constraint (^0.5: 0.5.0 => 0.5.1) leaves the castor vendor silently stale.
 */
function updateCastorTasks(): void
{
    io()->section('Updating the castor tasks');
    // the "composer" alias may belong to tapomix-composer, so qualify the command
    $command = ['castor', 'castor:composer', 'install'];

    // the castor vendor never affects the service: a failure (network, GitHub) must not block its update
    if (!run($command, context()->withAllowFailure())->isSuccessful()) {
        io()->warning(\sprintf('Castor tasks not updated, the service update goes on: run "%s" by hand.', \implode(' ', $command)));

        return;
    }

    // this run already loaded the previous tasks: the new ones apply from the next run
    io()->note('Castor tasks updated: they apply from the next castor run.');
}

/**
 * Hash a file, or null when it is absent.
 *
 * Same helper as in the infra tasks, not shared: the service preset does not import them.
 */
function hashFile(string $path): ?string
{
    if (!\is_file($path)) {
        return null;
    }

    return \md5_file($path) ?: null;
}
