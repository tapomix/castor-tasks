<?php

declare(strict_types=1);

namespace Tapomix\Castor\Vps;

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Castor\variable;
use function Tapomix\Castor\Docker\build as docker_build;
use function Tapomix\Castor\Docker\pull as docker_pull;
use function Tapomix\Castor\Docker\start as docker_start;
use function Tapomix\Castor\Docker\stop as docker_stop;
use function Tapomix\Castor\Laravel\artisan;
use function Tapomix\Castor\Symfony\console;

define('TAPOMIX_NAMESPACE_VPS', 'tapomix-vps');

#[AsTask(namespace: TAPOMIX_NAMESPACE_VPS, description: 'Update and restart the app on the VPS', aliases: ['vps:deploy'], enabled: EXPR_ENV_PROD)]
function deploy(): void
{
    io()->title('Deploying latest code on the VPS');

    io()->section('Pulling latest code');
    run(['git', 'pull']);

    // Apps may keep code directly at the repo root (APP_CODE_PATH=.) instead of a submodule
    if (fs()->exists(PathHelper::getRoot() . '/.gitmodules')) {
        io()->section('Updating submodules');
        run(['git', 'submodule', 'update', '--init', '--recursive']);
    }

    io()->section('Pulling images');
    docker_pull(); // non-buildable images only: build() pulls the base images itself

    io()->section('Building images');
    docker_build();

    io()->section('Restarting containers');
    docker_stop();
    docker_start();

    runMigrations();

    io()->success('Deployment complete.');
}

function runMigrations(): void
{
    $framework = (string) variable('APP.FRAMEWORK');

    io()->section('Running migrations');

    match ($framework) {
        'symfony' => console(['doctrine:migrations:migrate', '--no-interaction']),
        'laravel' => artisan(['migrate', '--force']),
        default => io()->text(\sprintf('No migration step for framework "%s", skipping.', $framework)),
    };
}
