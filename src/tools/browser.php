<?php

declare(strict_types=1);

namespace Tapomix\Castor\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\variable;
use function Tapomix\Castor\Docker\start;

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

#[AsTask(namespace: TAPOMIX_NAMESPACE_TOOLS, description: 'Start all services and open app in browser', aliases: ['dev'], enabled: EXPR_ENV_DEV)]
function dev(
    #[AsOption(shortcut: 'u', mode: InputOption::VALUE_NEGATABLE, description: 'Pull and build the images first')]
    bool $update = true,
    ?string $browser = null,
): void {
    // a failing start stops the task: the browser never opens on a dead stack
    start($update);
    browser($browser);
}
