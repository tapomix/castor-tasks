<?php

namespace Tapomix\Castor\Tests\Unit\Infra;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Infra\computeFilesToSync;

class SyncTest extends TestCase
{
    public function testComputeFilesToSyncReturnsAllFrozenFilesWhenNoExclusions(): void
    {
        $frozenFiles = ['docker-compose.yml', '.docker/php/Dockerfile', '.github/workflows/ci.yml'];

        $result = computeFilesToSync($frozenFiles, []);

        $this->assertSame($frozenFiles, $result);
    }

    public function testComputeFilesToSyncExcludesSpecifiedFiles(): void
    {
        $frozenFiles = ['docker-compose.yml', '.docker/php/Dockerfile', '.github/workflows/ci.yml'];
        $excludedFiles = ['.github/workflows/ci.yml'];

        $result = computeFilesToSync($frozenFiles, $excludedFiles);

        $this->assertSame(['docker-compose.yml', '.docker/php/Dockerfile'], $result);
    }

    public function testComputeFilesToSyncExcludesMultipleFiles(): void
    {
        $frozenFiles = ['docker-compose.yml', '.docker/php/Dockerfile', '.github/workflows/ci.yml'];
        $excludedFiles = ['docker-compose.yml', '.github/workflows/ci.yml'];

        $result = computeFilesToSync($frozenFiles, $excludedFiles);

        $this->assertSame(['.docker/php/Dockerfile'], $result);
    }

    public function testComputeFilesToSyncReturnsEmptyArrayWhenAllFilesExcluded(): void
    {
        $frozenFiles = ['docker-compose.yml', '.docker/php/Dockerfile'];
        $excludedFiles = ['docker-compose.yml', '.docker/php/Dockerfile'];

        $result = computeFilesToSync($frozenFiles, $excludedFiles);

        $this->assertSame([], $result);
    }

    public function testComputeFilesToSyncReturnsEmptyArrayWhenFrozenListIsEmpty(): void
    {
        $result = computeFilesToSync([], []);

        $this->assertSame([], $result);
    }

    public function testComputeFilesToSyncIgnoresExclusionsNotInFrozenList(): void
    {
        $frozenFiles = ['docker-compose.yml'];
        $excludedFiles = ['some-unknown-file.yml', 'another-unknown-file.php'];

        $result = computeFilesToSync($frozenFiles, $excludedFiles);

        $this->assertSame(['docker-compose.yml'], $result);
    }

    public function testComputeFilesToSyncReturnsReindexedArray(): void
    {
        $frozenFiles = ['file-a.yml', 'file-b.yml', 'file-c.yml'];
        $excludedFiles = ['file-a.yml'];

        $result = computeFilesToSync($frozenFiles, $excludedFiles);

        // Ensure keys are re-indexed (0, 1, ...) not (1, 2, ...)
        $this->assertSame(0, array_key_first($result));
        $this->assertCount(2, $result);
        $this->assertSame(['file-b.yml', 'file-c.yml'], $result);
    }

    public function testComputeFilesToSyncHandlesEmptyFrozenListWithExclusions(): void
    {
        $result = computeFilesToSync([], ['file-a.yml', 'file-b.yml']);

        $this->assertSame([], $result);
    }
}
