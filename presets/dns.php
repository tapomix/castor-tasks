<?php

use function Castor\import;

// import only dns related tasks

import(__DIR__ . '/../src/Enums'); // required by dns tasks
import(__DIR__ . '/../src/dns');
