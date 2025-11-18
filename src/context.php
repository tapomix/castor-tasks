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
    if ( // constant defined in castor.php from app
        \defined('TAPOMIX_APP_ENV_FILE')
        && fs()->exists(TAPOMIX_APP_ENV_FILE)
    ) {
        // load app env first to enable variable expansion
        load_dot_env(TAPOMIX_APP_ENV_FILE);
    }

    $castorEnvFile = '.castor/.env.castor';

    if (fs()->exists($castorEnvFile)) {
        load_dot_env($castorEnvFile);
    }

    $data = [
        // from castor specific env vars
        'APP.CODE_PATH' => $_SERVER['APP_CODE_PATH'] ?? 'code',
        'APP.FRAMEWORK' => $_SERVER['APP_FRAMEWORK'] ?? 'vanilla',

        'CASTOR.DEFAULT_BROWSER' => $_SERVER['CASTOR_DEFAULT_BROWSER'] ?? 'firefox-developer-edition',

        'DOCKER.ENV_FILE' => \defined('TAPOMIX_APP_ENV_FILE') ? TAPOMIX_APP_ENV_FILE : '.env.docker',
        'DOCKER.SERVICES.DB' => $_SERVER['APP_SERVICE_DB'] ?? 'db',
        'DOCKER.SERVICES.NODE' => $_SERVER['APP_SERVICE_NODE'] ?? 'node',
        'DOCKER.SERVICES.PHP' => $_SERVER['APP_SERVICE_PHP'] ?? 'php',

        // from app specific env vars
        'APP.ENVIRONMENT' => $_SERVER['APP_ENVIRONMENT'] ?? 'dev',
        'APP.SERVER_NAME' => $_SERVER['APP_SERVER_NAME'] ?? 'localhost',
    ];

    return new Context(
        data: $data,
        tty: Process::isTtySupported(),
        allowFailure: true,
    );
}
