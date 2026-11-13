<?php

declare(strict_types=1);

namespace Tapomix\Castor\Service;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;
use function Tapomix\Castor\CastorLock\installOnLockChange;
use function Tapomix\Castor\CastorLock\lockHash;
use function Tapomix\Castor\Docker\buildBaseDockerComposeCmd;
use function Tapomix\Castor\Docker\composeContext;
use function Tapomix\Castor\Docker\hasBuildableServices;

// loaded here, not by the presets: a project may import this file alone (the template imports infra/sync.php);
// require_once, like castor import(), so the file is loaded once whatever the path
require_once __DIR__ . '/../castor-lock.php';

define('TAPOMIX_NAMESPACE_SERVICE', 'tapomix-service');

/**
 * Plain `docker compose`, shared with the docker tasks: the service `.env` picks the compose files (COMPOSE_FILE).
 */
#[AsTask(namespace: TAPOMIX_NAMESPACE_SERVICE, description: 'Update and restart a Docker service', aliases: ['service:update'])]
function update(): void
{
    io()->title('Updating the service');

    // first, before touching anything: COMPOSE_FILE must be set, docker compose would load compose.yaml alone
    $compose = buildBaseDockerComposeCmd();

    // read the lock before the pull, to detect the one the pull is about to bring
    $castorLockBefore = lockHash();

    io()->section('Pulling latest code');
    run(['git', 'pull']);

    installOnLockChange($castorLockBefore);

    io()->section('Pulling images');
    run([...$compose, 'pull', '--ignore-buildable'], context: composeContext());

    io()->section('Building images');
    if (hasBuildableServices()) {
        run([...$compose, 'build', '--pull'], context: composeContext());
    } else {
        // an image-only service: docker compose would only warn "No services to build"
        io()->text('No buildable service, skipped.');
    }

    io()->section('Restarting containers');
    // always recreate: Compose does not watch the mounted config files, a plain `up` would keep the old config.
    // the short cut is accepted
    run([...$compose, 'up', '--detach', '--wait', '--force-recreate'], context: composeContext());

    io()->success('Service updated.');
}
