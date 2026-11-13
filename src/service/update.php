<?php

declare(strict_types=1);

namespace Tapomix\Castor\Service;

use Castor\Attribute\AsTask;

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

    io()->section('Pulling latest code');
    run(['git', 'pull']);

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
