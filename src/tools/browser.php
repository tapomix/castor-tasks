<?php

namespace Tapomix\Castor\Tools;

use Castor\Attribute\AsTask;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\variable;

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Open app in browser', aliases: ['browser', 'open'], enabled: EXPR_ENV_DEV)]
function browser(?string $browser = null): void
{
    $browser ??= (string) variable('CASTOR.DEFAULT_BROWSER');
    $serverName = (string) variable('APP.SERVER_NAME');

    // test if browser executable exist
    $finder = new ExecutableFinder();
    if (null === $finder->find($browser)) {
        throw new \RuntimeException('Browser executable not found.');
    }

    // launch custom browser in background + detach from process
    capture(\sprintf('%s %s &', $browser, \escapeshellarg('https://' . $serverName)));
}
