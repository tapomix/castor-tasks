<?php

namespace Tapomix\Dev;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\io;
use function Castor\run;

define('TAPOMIX_NAMESPACE_DEV', 'tapomix-dev');

#[AsContext(default: true)]
function default_context(): Context
{
    return new Context(
        allowFailure: true,
        tty: Process::isTtySupported(),
    );
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DEV, description: 'Run QA tools', default: true, aliases: ['qa'])]
function qa(): void
{
    io()->info('Run QA tools');

    run(baseContainerCmd()); // no specific command as it's the default CMD oin the Dockerfile
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_DEV, description: 'Execute a composer command', aliases: ['composer'])]
function composer(
    #[AsRawTokens]
    array $args = [],
): void {
    run(\array_merge(baseContainerCmd(), ['composer'], $args));
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_DEV, description: 'Run PHPUnit tests', aliases: ['test', 'tests'])]
function phpunit(
    #[AsRawTokens]
    array $args = [],
): void {
    io()->info('Running PHPUnit tests');

    run(\array_merge(baseContainerCmd(), ['vendor/bin/phpunit'], $args));
}

/** @return string[] */
function baseContainerCmd(): array
{
    return [
        'docker',
        'compose',
        'run',
        '--rm',
        'php-qa', // @see service name in compose.yaml
    ];
}
