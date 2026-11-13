<?php

use function Castor\import;

// import only the generic Docker service tasks

import(__DIR__ . '/../src/check.php');
import(__DIR__ . '/../src/context.php');
import(__DIR__ . '/../src/docker.php'); // start, stop, logs, build, pull, shell: the same commands as the sites
import(__DIR__ . '/../src/service');
