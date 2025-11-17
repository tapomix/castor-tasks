<?php

namespace tapomix\castor\tools;

use Castor\Attribute\AsTask;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\variable;

#[AsTask(namespace: 'tapomix-tools', description: 'Open app in browser', aliases: ['browser', 'open'])]
function browser(?string $browser = null): void
{
    $browser ??= (string) variable('TAPOMIX.DEFAULT_BROWSER');
    $serverName = (string) variable('APP.SERVER_NAME', 'localhost');

    // test if browser executable exist
    $finder = new ExecutableFinder();
    if (null === $finder->find($browser)) {
        throw new \RuntimeException('Browser executable not found.');
    }

    // launch custom browser in background + detach from process
    capture(\sprintf('%s %s &', $browser, \escapeshellarg('https://' . $serverName)));
}
