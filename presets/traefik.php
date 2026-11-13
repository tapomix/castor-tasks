<?php

use function Castor\import;

// import only traefik related tasks: a Traefik repo imports the service preset too

import(__DIR__ . '/../src/context.php'); // required by traefik tasks (EXPR_ENV_DEV)
import(__DIR__ . '/../src/traefik');
