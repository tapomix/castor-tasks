<?php

namespace tapomix\castor\composer;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;
use function tapomix\castor\docker\exec as docker_exec;

define('TAPOMIX_NAMESPACE_COMPOSER', 'tapomix-composer');

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a composer command', aliases: ['composer'])]
function exec(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('TAPOMIX.SERVICES.PHP'), \array_merge(['composer'], $args));
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a compose command for dev', aliases: ['composer:dev'])]
function execDev(
    #[AsRawTokens]
    array $args = [],
): void {
    exec(\array_merge($args, ['--dev']));
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a global compose command', aliases: ['composer:global'])]
function execGlobal(
    #[AsRawTokens]
    array $args = [],
): void {
    exec(\array_merge(['global'], $args));
}
