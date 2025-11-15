<?php

namespace tapomix\dev;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Run QA tools', default: true, aliases: ['qa'])]
function qa(): void
{
    io()->info('Run QA tools');

    run(baseContainerCmd(), context: context()->withAllowFailure());
}

/** @param string[] $args */
#[AsTask(description: 'Execute a composer command', aliases: ['composer'])]
function composer(
    #[AsRawTokens]
    array $args = [],
): void {
    run(\array_merge(baseContainerCmd(), ['composer'], $args));
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
