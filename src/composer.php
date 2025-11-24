<?php

namespace Tapomix\Castor\Composer;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;

use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;

define('TAPOMIX_NAMESPACE_COMPOSER', 'tapomix-composer');

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a composer command', aliases: ['composer'])]
function exec(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('DOCKER.SERVICES.PHP'), ['composer', ...$args]);
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a compose command for dev', aliases: ['composer:dev'])]
function execDev(
    #[AsRawTokens]
    array $args = [],
): void {
    if ([] === $args) {
        throw new ProblemException('At least one argument is required for composer:dev (e.g., "install", "update")');
    }

    $args[] = '--dev';
    exec($args);
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a global compose command', aliases: ['composer:global'])]
function execGlobal(
    #[AsRawTokens]
    array $args = [],
): void {
    if ([] === $args) {
        throw new ProblemException('At least one argument is required for composer:global (e.g., "require vendor/package")');
    }

    exec(['global', ...$args]);
}
