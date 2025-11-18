<?php

namespace tapomix\castor\qa;

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\io;
use function Castor\parallel;
use function Castor\variable;

define('TAPOMIX_NAMESPACE_QA', 'tapomix-qa');

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run all QA analyzers', aliases: ['qa'])]
function all(bool $parallel = false): int
{
    io()->title('Running all analyzers ');

    $tools = listTools();

    if ([] === $tools) {
        io()->warning('No QA analyzer found');

        return 0;
    }

    $processes = [];

    if ($parallel) {
        $processes = parallel(
            ...\array_map(
                fn (string $fn): \Closure => (fn (): ?Process => $fn()),
                $tools
            )
        );
    } else {
        foreach ($tools as $fn) {
            $processes[] = $fn();
        }
    }

    if ([] === $processes) {
        return 0;
    }

    return \max(
        \array_map(
            fn (?Process $process): int => $process?->getExitCode() ?? 0,
            $processes
        )
    );
}

/** @return callable-string[] */
function listTools(string $namespace = 'tapomix\castor\qa\analyzer'): array
{
    $functions = \get_defined_functions()['user'];

    return \array_filter($functions, fn (string $fn): bool => \str_starts_with($fn, $namespace . '\\'));
}

function buildLocalPath(string $binary): string
{
    $root = context()->workingDirectory ?? PathHelper::getRoot();

    return $root . '/' . variable('APP.CODE_PATH') . '/' . $binary;
}
