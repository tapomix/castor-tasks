<?php

namespace tapomix\castor;

use Castor\Attribute\AsContext;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\fs;
use function Castor\load_dot_env;

define('TAPOMIX_DEFAULT_CONTEXT', 'tapomix-default');

#[AsContext(name: TAPOMIX_DEFAULT_CONTEXT)] // don't defined as default to allow override
function default_context(): Context
{
    $castorEnvFile = '.castor/.env.castor';

    if (fs()->exists($castorEnvFile)) {
        load_dot_env($castorEnvFile);
    }

    $data = [
        'TAPOMIX.DEFAULT_BROWSER' => $_SERVER['TAPOMIX_DEFAULT_BROWSER'] ?? 'firefox-developer-edition',

        'TAPOMIX.SERVICES.DB' => $_SERVER['TAPOMIX_SERVICE_DB'] ?? 'db',
        'TAPOMIX.SERVICES.NODE' => $_SERVER['TAPOMIX_SERVICE_NODE'] ?? 'node',
        'TAPOMIX.SERVICES.PHP' => $_SERVER['TAPOMIX_SERVICE_PHP'] ?? 'php',
    ];

    return new Context(
        data: $data,
        tty: Process::isTtySupported(),
        allowFailure: true,
    );
}
