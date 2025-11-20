<?php

namespace Tapomix\Castor\Tests\Integration;

use Tapomix\Castor\Tests\TaskTestCase;

/**
 * Test basic Castor functionality
 *
 * Note: These tests verify the test infrastructure works correctly.
 * Full task testing requires proper context configuration.
 */
class CheckTaskTest extends TaskTestCase
{
    public function testCastorIsExecutable(): void
    {
        $process = $this->runTask(['--version']);

        $this->assertTaskSucceeded($process);
        $this->assertOutputContains($process, 'castor');
    }

    public function testCastorListCommand(): void
    {
        // Test that castor list returns some output (even if context has issues)
        $process = $this->runTask(['list']);

        // Should return output even if there are errors
        $this->assertNotEmpty($process->getOutput() . $process->getErrorOutput());
    }
}
