<?php

namespace tapomix\castor\symfony;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;

use function tapomix\castor\docker\exec as docker_exec;

/** @param string[] $args */
#[AsTask(namespace: 'tapomix-symfony', description: 'Execute a Symfony Console command', aliases: ['console'])]
function console(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('TAPOMIX.SERVICES.PHP'), \array_merge(['php', 'bin/console'], $args));
}
