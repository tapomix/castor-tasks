<?php

namespace Tapomix\Castor\Dns;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\io;

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, description: 'Execute a DiG command', aliases: ['dig'])]
function query(
    #[AsRawTokens]
    array $args = [],
): void {
    if ([] === $args) {
        // throw new \InvalidArgumentException('DiG command can not be empty');
        io()->error('DiG command can not be empty');

        return;
    }

    $process = dns_run(['dig', ...$args]);
    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Unable to run DiG command in container');
    }

    echo $process->getOutput();
}
