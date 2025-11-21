<?php

namespace Tapomix\Castor\Qa;

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

use function Castor\app;
use function Castor\context;
use function Castor\io;
use function Castor\parallel;
use function Castor\run;
use function Castor\variable;

define('TAPOMIX_NAMESPACE_QA', 'tapomix-qa');

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run all QA analyzers', aliases: ['qa'], enabled: EXPR_ENV_DEV)]
function all(bool $parallel = false): int
{
    io()->title('Running all analyzers ');

    $analyzers = listAnalyzers();

    /** @var string[] $taskNames */
    $taskNames = \array_values( // keep only values
        \array_filter( // skip command with null name
            \array_map( // extract command name
                fn (Command $cmd): ?string => $cmd->getName(),
                $analyzers,
            ),
            fn (?string $name): bool => null !== $name,
        )
    );

    if ([] === $taskNames) {
        io()->warning('No QA analyzer found');

        return 0;
    }

    $processes = [];

    if ($parallel) {
        $processes = parallel(
            ...\array_map(
                fn (string $taskName): \Closure => fn (): Process => runCastorCommand($taskName),
                $taskNames
            )
        );
    } else {
        foreach ($taskNames as $taskName) {
            $processes[] = runCastorCommand($taskName);
        }
    }

    if ([] === $processes) {
        return 0;
    }

    return \max(
        \array_map(
            fn (Process $process): int => $process->getExitCode() ?? 0,
            $processes
        )
    );
}

function runCastorCommand(string $command): Process
{
    return run(['castor', $command]);
}

/** @return Command[] */
function listAnalyzers(string $namespace = TAPOMIX_NAMESPACE_QA): array
{
    $tasks = app()->all($namespace);

    return \array_filter($tasks, fn (Command $cmd): bool => $namespace . ':all' !== $cmd->getName());
}

function buildLocalPath(string $binary): string
{
    $root = context()->workingDirectory ?? PathHelper::getRoot();

    return $root . '/' . variable('APP.CODE_PATH') . '/' . $binary;
}
