<?php

namespace Tapomix\Castor\Tests\Unit\Vps;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Vps\detectFramework;
use function Tapomix\Castor\Vps\dotenvProblems;
use function Tapomix\Castor\Vps\envFromPath;
use function Tapomix\Castor\Vps\hasAppSecret;
use function Tapomix\Castor\Vps\prefillDotenv;
use function Tapomix\Castor\Vps\writeSecretFile;

class InitTest extends TestCase
{
    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            @unlink($this->dir . '/bin/console');
            @rmdir($this->dir . '/bin');
            @unlink($this->dir . '/artisan');
            rmdir($this->dir);
            $this->dir = null;
        }
    }

    public function testPrefillReplacesTheValueAndDropsTheInlineComment(): void
    {
        $this->assertSame(
            "CONTAINER_NAME=bank\nAPP_ENV=prod\n",
            prefillDotenv("CONTAINER_NAME= # ! <-- FILL ME\nAPP_ENV=dev # dev | prod\n", ['CONTAINER_NAME' => 'bank', 'APP_ENV' => 'prod']),
        );
    }

    public function testPrefillLeavesCommentedLinesAndAppendsMissingKeys(): void
    {
        $this->assertSame(
            "# DB_PASS=x\nDB_PASS=s3cret\nTZ=UTC\nNEW=1\n",
            prefillDotenv("# DB_PASS=x\nDB_PASS=ChangeMe! # ! <-- EDIT IN PROD\nTZ=UTC\n", ['DB_PASS' => 's3cret', 'NEW' => '1']),
        );
    }

    public function testPrefillKeepsTheInterpolationOfAValue(): void
    {
        $this->assertSame(
            "TRAEFIK_HOST=\${CONTAINER_NAME}.example.com\n",
            prefillDotenv("TRAEFIK_HOST=\${CONTAINER_NAME}.domain.ext # .docker.dev\n", ['TRAEFIK_HOST' => '${CONTAINER_NAME}.example.com']),
        );
    }

    public function testEnvFromPath(): void
    {
        $this->assertSame(
            ['CONTAINER_NAME' => 'bank', 'APP_ENV' => 'prod', 'TRAEFIK_HOST' => '${CONTAINER_NAME}.example.com'],
            envFromPath('/srv/web/example.com/bank'),
        );
    }

    public function testEnvFromPathAtTheRootDoesNotThrow(): void
    {
        $this->assertSame('bank', envFromPath('/bank')['CONTAINER_NAME']);
    }

    public function testDetectSymfony(): void
    {
        mkdir($this->tempDir() . '/bin');
        touch($this->dir . '/bin/console');

        $this->assertSame('symfony', detectFramework($this->dir));
    }

    public function testDetectLaravel(): void
    {
        touch($this->tempDir() . '/artisan');

        $this->assertSame('laravel', detectFramework($this->dir));
    }

    public function testDetectVanilla(): void
    {
        $this->assertSame('vanilla', detectFramework($this->tempDir()));
    }

    public function testDotenvWithoutProblems(): void
    {
        $this->assertSame([], dotenvProblems(['CONTAINER_NAME' => 'bank', 'APP_ENV' => 'prod', 'DB_PASS' => 'abc']));
    }

    public function testDotenvProblems(): void
    {
        $this->assertSame(
            ['CONTAINER_NAME is empty', 'APP_ENV is "dev", expected "prod"', 'DB_PASS is still the .env.dist default'],
            dotenvProblems(['CONTAINER_NAME' => '', 'APP_ENV' => 'dev', 'DB_PASS' => 'ChangeMe!']),
        );
    }

    public function testDotenvProblemsOnMissingValues(): void
    {
        $this->assertSame(
            ['CONTAINER_NAME is empty', 'APP_ENV is not set', 'DB_PASS is still the .env.dist default'],
            dotenvProblems([]),
        );
    }

    public function testHasAppSecret(): void
    {
        $this->assertTrue(hasAppSecret("APP_SECRET=abc\nOTHER=1\n"));
    }

    /** @return iterable<string, array{string}> */
    public static function withoutAppSecret(): iterable
    {
        yield 'empty' => ["APP_SECRET=\n"];
        yield 'commented' => ["# APP_SECRET=abc\n"];
        yield 'absent' => ["OTHER=1\n"];
    }

    #[DataProvider('withoutAppSecret')]
    public function testHasNoAppSecret(string $content): void
    {
        $this->assertFalse(hasAppSecret($content));
    }

    public function testSecretFileIsReadableByItsOwnerOnly(): void
    {
        $path = $this->tempDir() . '/.env';
        writeSecretFile($path, "DB_PASS=abc\n");

        try {
            $this->assertSame("DB_PASS=abc\n", file_get_contents($path));
            $this->assertSame(0o600, fileperms($path) & 0o777);
        } finally {
            unlink($path);
        }
    }

    private function tempDir(): string
    {
        $this->dir = sys_get_temp_dir() . '/init-test-' . uniqid();
        mkdir($this->dir);

        return $this->dir;
    }
}
