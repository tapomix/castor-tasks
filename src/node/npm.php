<?php

namespace tapomix\castor\node;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;

use function tapomix\castor\docker\exec as docker_exec;

/** @param string[] $args */
#[AsTask(namespace: 'tapomix-node', description: 'Execute Npm command', aliases: ['npm'])]
function npm(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('DOCKER.SERVICES.NODE'), \array_merge(['npm'], $args));
}
