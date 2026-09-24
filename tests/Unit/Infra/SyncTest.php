<?php

namespace Tapomix\Castor\Tests\Unit\Infra;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Infra\computeFilesToSync;
use function Tapomix\Castor\Infra\hashFile;
use function Tapomix\Castor\Infra\parseFrozenDiff;

class SyncTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->tempFiles = [];
    }

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

    public function testHashFileReturnsNullWhenFileIsAbsent(): void
    {
        $result = hashFile(sys_get_temp_dir() . '/castor-sync-test-absent.lock');

        $this->assertNull($result);
    }

    public function testHashFileReturnsTheFileHash(): void
    {
        $path = $this->createTempFile('locked content');

        $result = hashFile($path);

        $this->assertSame(md5('locked content'), $result);
    }

    public function testHashFileReturnsADifferentHashWhenContentChanges(): void
    {
        $path = $this->createTempFile('castor-tasks 0.3.4');
        $before = hashFile($path);

        file_put_contents($path, 'castor-tasks 0.3.6');
        clearstatcache(true, $path);

        $this->assertNotSame($before, hashFile($path));
    }

    public function testHashFileReturnsTheSameHashWhenContentIsUnchanged(): void
    {
        $path = $this->createTempFile('castor-tasks 0.3.6');

        $this->assertSame(hashFile($path), hashFile($path));
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'castor-sync-test-');
        $this->assertIsString($path);

        $this->tempFiles[] = $path;
        file_put_contents($path, $content);

        return $path;
    }

    public function testParseFrozenDiffMapsEachFileToItsState(): void
    {
        $output = "M\tcompose.yaml\nD\t.claude/rules/new-rule.md\nT\t.editorconfig\n";

        $this->assertSame([
            'compose.yaml' => 'differs',
            '.claude/rules/new-rule.md' => 'missing locally',
            '.editorconfig' => 'differs',
        ], parseFrozenDiff($output));
    }

    public function testParseFrozenDiffReturnsEmptyArrayWithoutDifferences(): void
    {
        $this->assertSame([], parseFrozenDiff(''));
        $this->assertSame([], parseFrozenDiff("\n"));
    }
}
