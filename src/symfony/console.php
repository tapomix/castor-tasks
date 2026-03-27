<?php

namespace Tapomix\Castor\Symfony;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_SYMFONY, description: 'Execute a Symfony Console command', aliases: ['console', 'symfony:console'], enabled: EXPR_FRAMEWORK_SYMFONY)]
function console(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('DOCKER.SERVICES.PHP'), ['php', 'bin/console', ...$args]);
}
