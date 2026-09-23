<?php

// Guard against redefinition when this file is imported/mounted by another castor.php
defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Helper\PathHelper;

use function Castor\import;

// Note: We use a direct path import (not remote-import) to preserve the working directory
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/all.php'); // import all tasks

// Optional: Define app environment file location
// define('TAPOMIX_APP_ENV_FILE', '.env'); // default: .env.docker
