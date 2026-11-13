<?php

use function Castor\import;

// import only php related tasks

// a site: its COMPOSE_FILE must be exactly the four site levels (see validateComposeFile() in src/docker.php)
define('TAPOMIX_PROJECT_SITE', true);

import(__DIR__ . '/../src/db');
import(__DIR__ . '/../src/infra');
import(__DIR__ . '/../src/laravel');
import(__DIR__ . '/../src/qa');
import(__DIR__ . '/../src/symfony');
import(__DIR__ . '/../src/tools');
import(__DIR__ . '/../src/vps/deploy.php');
import(__DIR__ . '/../src/vps/init.php');

import(__DIR__ . '/../src/check.php');
import(__DIR__ . '/../src/composer.php');
import(__DIR__ . '/../src/context.php');
import(__DIR__ . '/../src/docker.php');
