<?php

use Castor\Helper\PathHelper;

use function Castor\import;

// Note: We use a direct path import (not remote-import) to preserve the working directory
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/src/'); // import all tasks

// Optional: Define app environment file location
// define('TAPOMIX_APP_ENV_FILE', '.env'); // default: .env.docker
