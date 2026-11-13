<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\CastorLock\hashFile;

class CastorLockTest extends TestCase
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

    public function testHashFileReturnsNullWhenFileIsAbsent(): void
    {
        $result = hashFile(sys_get_temp_dir() . '/castor-lock-test-absent.lock');

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
        $path = tempnam(sys_get_temp_dir(), 'castor-lock-test-');
        $this->assertIsString($path);

        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
