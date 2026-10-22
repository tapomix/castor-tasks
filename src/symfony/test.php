<?php

declare(strict_types=1);

namespace Tapomix\Castor\Symfony;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;

#[AsTask(namespace: TAPOMIX_NAMESPACE_SYMFONY, description: 'Run PHPUnit tests', aliases: ['test'], enabled: EXPR_FRAMEWORK_SYMFONY . ' && ' . EXPR_ENV_DEV)]
function test(
    #[AsOption(shortcut: 'f', description: 'Only run tests matching this pattern')]
    string $filter = '',
    #[AsOption(description: 'Generate a code coverage report')]
    bool $coverage = false,
): int {
    $cmd = ['vendor/bin/phpunit'];

    if ('' !== $filter) {
        $cmd = [...$cmd, '--filter', $filter];
    }

    if ($coverage) {
        $cmd[] = '--coverage-text';
    }

    io()->title('Running PHPUnit' . ($coverage ? ' with coverage' : ''));

    // XDEBUG_MODE overrides xdebug.mode from the ini, so coverage works even when the
    // project trimmed it out of its dev config for speed — and only for this run.
    // Castor ignores a returned Process: only an int becomes the exit code
    return docker_exec(
        (string) variable('DOCKER.SERVICES.PHP'),
        $cmd,
        context()->withAllowFailure(),
        $coverage ? ['XDEBUG_MODE' => 'coverage'] : [],
    )->getExitCode() ?? 0;
}
