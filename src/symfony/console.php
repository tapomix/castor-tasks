<?php

namespace Tapomix\Castor\Symfony;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;

/** @param string[] $args */
#[AsTask(namespace: 'tapomix-symfony', description: 'Execute a Symfony Console command', aliases: ['console'], enabled: EXPR_FRAMEWORK_SYMFONY)]
function console(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('DOCKER.SERVICES.PHP'), \array_merge(['php', 'bin/console'], $args));
}
