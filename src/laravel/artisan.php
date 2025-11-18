<?php

namespace tapomix\castor\laravel;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\variable;
use function tapomix\castor\docker\exec as docker_exec;

/** @param string[] $args */
#[AsTask(namespace: 'tapomix-laravel', description: 'Execute a Laravel Artisan command', aliases: ['artisan'], enabled: EXPR_FRAMEWORK_LARAVEL)]
function artisan(
    #[AsRawTokens]
    array $args = [],
): void {
    docker_exec((string) variable('DOCKER.SERVICES.PHP'), \array_merge(['php', 'artisan'], $args));
}
