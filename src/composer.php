<?php

declare(strict_types=1);

namespace Tapomix\Castor\Composer;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;

use function Castor\context;
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

/** Major.minor PHP version running in the given service (e.g. `8.5`) */
function getContainerPhpVersion(string $service): string
{
    // no TTY: a TTY process streams its output to the terminal and leaves nothing to read
    $process = docker_exec(
        $service,
        ['php', '-r', 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'],
        context()->withTty(false)->withQuiet(),
    );

    $version = \trim($process->getOutput());

    if (1 !== \preg_match('/^\d+\.\d+$/', $version)) {
        throw new ProblemException(\sprintf('Unable to read the PHP version of the "%s" service.', $service));
    }

    return $version;
}
