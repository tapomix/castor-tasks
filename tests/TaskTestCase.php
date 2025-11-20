<?php

namespace Tapomix\Castor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

abstract class TaskTestCase extends TestCase
{
    protected static string $castorBin;
    protected static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        // Always use absolute path to castor binary from project root
        self::$castorBin = $_SERVER['CASTOR_BIN'] ?? self::$projectRoot . '/vendor/bin/castor';
    }

    /**
     * Run a Castor task and return the process
     *
     * @param array<string> $args Task arguments (e.g., ['tapomix-docker:build', '--no-cache'])
     * @param string|null $cwd Working directory (defaults to project root)
     * @param array<string, string> $env Additional environment variables
     */
    protected function runTask(array $args, ?string $cwd = null, array $env = []): Process
    {
        $workingDirectory = $cwd ?? self::$projectRoot;

        $process = new Process(
            [self::$castorBin, '--no-ansi', ...$args],
            cwd: $workingDirectory,
            env: array_merge([
                'COLUMNS' => 1000,
                'CASTOR_NO_REMOTE' => '1',
            ], $env),
            timeout: 60
        );

        $process->run();

        return $process;
    }

    /**
     * Assert that a task succeeded (exit code 0)
     */
    protected function assertTaskSucceeded(Process $process, string $message = ''): void
    {
        $this->assertSame(0, $process->getExitCode(), $message ?: sprintf(
            "Task failed with exit code %d.\nOutput: %s\nError: %s",
            $process->getExitCode(),
            $process->getOutput(),
            $process->getErrorOutput()
        ));
    }

    /**
     * Assert that a task failed (exit code non-zero)
     */
    protected function assertTaskFailed(Process $process, string $message = ''): void
    {
        $this->assertNotSame(0, $process->getExitCode(), $message ?: 'Task was expected to fail but succeeded');
    }

    /**
     * Assert that output contains a string
     */
    protected function assertOutputContains(Process $process, string $needle, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $process->getOutput(), $message);
    }

    /**
     * Assert that error output contains a string
     */
    protected function assertErrorContains(Process $process, string $needle, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $process->getErrorOutput(), $message);
    }
}
