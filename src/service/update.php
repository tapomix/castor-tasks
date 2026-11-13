<?php

declare(strict_types=1);

namespace Tapomix\Castor\Service;

use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;
use Symfony\Component\Dotenv\Dotenv;

use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Tapomix\Castor\CastorLock\installOnLockChange;
use function Tapomix\Castor\CastorLock\lockHash;

// loaded here, not by the presets: a project may import this file alone (the template imports infra/sync.php);
// require_once, like castor import(), so the file is loaded once whatever the path
require_once __DIR__ . '/../castor-lock.php';

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
    $castorLockBefore = lockHash();

    io()->section('Pulling latest code');
    run(['git', 'pull']);

    installOnLockChange($castorLockBefore);

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
