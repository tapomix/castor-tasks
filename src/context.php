<?php

namespace Tapomix\Castor;

use Castor\Attribute\AsContext;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\fs;
use function Castor\load_dot_env;

define('TAPOMIX_CASTOR_ENV_FILE', '.castor/.env.castor');
define('TAPOMIX_DEFAULT_CONTEXT', 'tapomix-default');

define('EXPR_FRAMEWORK_SYMFONY', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.FRAMEWORK'] === 'symfony'");
define('EXPR_FRAMEWORK_LARAVEL', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.FRAMEWORK'] === 'laravel'");

define('EXPR_ENV_DEV', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.ENVIRONMENT'] === 'dev'");
define('EXPR_ENV_PROD', "context(constant('TAPOMIX_DEFAULT_CONTEXT'))['APP.ENVIRONMENT'] === 'prod'");

#[AsContext(name: TAPOMIX_DEFAULT_CONTEXT)] // don't defined as default to allow override
function default_context(): Context
{
    if (!defined('TAPOMIX_APP_ENV_FILE')) { // constant may be defined in castor.php from app
        define('TAPOMIX_APP_ENV_FILE', '.env.docker');
    }

    // load app env first to enable variable interpolation
    if (fs()->exists(TAPOMIX_APP_ENV_FILE)) {
        load_dot_env(TAPOMIX_APP_ENV_FILE);
    }

    if (fs()->exists(TAPOMIX_CASTOR_ENV_FILE)) {
        load_dot_env(TAPOMIX_CASTOR_ENV_FILE);
    }

    $data = [
        // from castor specific env vars
        'APP.CODE_PATH' => $_SERVER['APP_CODE_PATH'] ?? 'code',
        'APP.FRAMEWORK' => $_SERVER['APP_FRAMEWORK'] ?? 'vanilla',

        'CASTOR.DEFAULT_BROWSER' => $_SERVER['CASTOR_DEFAULT_BROWSER'] ?? 'firefox-developer-edition',

        'DOCKER.ENV_FILE' => TAPOMIX_APP_ENV_FILE, // ! constant !
        'DOCKER.SERVICES.DB' => $_SERVER['APP_SERVICE_DB'] ?? 'db',
        'DOCKER.SERVICES.NODE' => $_SERVER['APP_SERVICE_NODE'] ?? 'node',
        'DOCKER.SERVICES.PHP' => $_SERVER['APP_SERVICE_PHP'] ?? 'php',

        // from app specific env vars
        'APP.DB.NAME' => $_SERVER['APP_DB_NAME'] ?? 'db',
        'APP.DB.USER' => $_SERVER['APP_DB_USER'] ?? 'user',
        'APP.ENVIRONMENT' => $_SERVER['APP_ENVIRONMENT'] ?? 'dev',
        'APP.NAME' => $_SERVER['APP_NAME'] ?? 'app',
        'APP.SERVER_NAME' => $_SERVER['APP_SERVER_NAME'] ?? 'localhost',
    ];

    return new Context(
        data: $data,
        tty: Process::isTtySupported(),
        allowFailure: true,
    );
}
