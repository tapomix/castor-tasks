<?php

namespace Tapomix\Castor\Tests\Unit\Vps;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Vps\codeCheckedOut;
use function Tapomix\Castor\Vps\detectFramework;
use function Tapomix\Castor\Vps\dotenvProblems;
use function Tapomix\Castor\Vps\envFromPath;
use function Tapomix\Castor\Vps\hasAppSecret;
use function Tapomix\Castor\Vps\networkState;
use function Tapomix\Castor\Vps\prefillDotenv;
use function Tapomix\Castor\Vps\userProblems;
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
        $this->assertSame([], dotenvProblems(['CONTAINER_NAME' => 'bank', 'APP_ENV' => 'prod', 'TRAEFIK_HOST' => 'bank.example.com', 'DB_PASS' => 'abc']));
    }

    public function testDotenvProblems(): void
    {
        $this->assertSame(
            ['CONTAINER_NAME is empty', 'APP_ENV is "dev", expected "prod"', 'TRAEFIK_HOST is "bank.domain.ext", expected <site>.<domain>', 'DB_PASS is still the .env.dist default'],
            dotenvProblems(['CONTAINER_NAME' => '', 'APP_ENV' => 'dev', 'TRAEFIK_HOST' => 'bank.domain.ext', 'DB_PASS' => 'ChangeMe!']),
        );
    }

    public function testDotenvProblemsOnMissingValues(): void
    {
        $this->assertSame(
            ['CONTAINER_NAME is empty', 'APP_ENV is not set', 'TRAEFIK_HOST is not set', 'DB_PASS is still the .env.dist default'],
            dotenvProblems([]),
        );
    }

    public function testTraefikHostWithoutDomainIsAProblem(): void
    {
        // a site one level below the root: TRAEFIK_HOST=${CONTAINER_NAME}. resolves to "bank."
        $this->assertSame(
            ['TRAEFIK_HOST is "bank.", expected <site>.<domain>'],
            dotenvProblems(['CONTAINER_NAME' => 'bank', 'APP_ENV' => 'prod', 'TRAEFIK_HOST' => 'bank.', 'DB_PASS' => 'abc']),
        );
    }

    public function testCodeNotCheckedOutWhenMissing(): void
    {
        $this->assertFalse(codeCheckedOut(sys_get_temp_dir() . '/init-test-absent-' . uniqid()));
    }

    public function testCodeNotCheckedOutWhenEmpty(): void
    {
        // an uninitialized submodule: git leaves an empty directory
        $this->assertFalse(codeCheckedOut($this->tempDir()));
    }

    public function testCodeCheckedOut(): void
    {
        touch($this->tempDir() . '/artisan');

        $this->assertTrue(codeCheckedOut($this->dir));
    }

    public function testUserMatchingTheVpsUserHasNoProblem(): void
    {
        $this->assertSame([], userProblems(['USER_ID' => '1001', 'GROUP_ID' => '1001'], 1001, 1001));
    }

    public function testUserOfAnotherMachineIsAProblem(): void
    {
        // the .env.dist default: the container would not own the files of the VPS user
        $this->assertSame(
            ['USER_ID is "1000", expected "1001" (the user running castor)', 'GROUP_ID is "1000", expected "1001" (the user running castor)'],
            userProblems(['USER_ID' => '1000', 'GROUP_ID' => '1000'], 1001, 1001),
        );
    }

    public function testUnsetUserIsAProblem(): void
    {
        $this->assertSame(['USER_ID is not set', 'GROUP_ID is not set'], userProblems([], 1001, 1001));
    }

    public function testNetworkPresent(): void
    {
        $this->assertSame('present', networkState(true, ''));
    }

    public function testNetworkMissing(): void
    {
        $this->assertSame('missing', networkState(false, 'Error response from daemon: network traefik-demo not found'));
    }

    public function testDockerUnavailableIsNotAMissingNetwork(): void
    {
        $this->assertSame('docker-unavailable', networkState(false, 'failed to connect to the docker API at unix:///var/run/docker.sock; check if the path is correct and if the daemon is running'));
        $this->assertSame('docker-unavailable', networkState(false, 'permission denied while trying to connect to the Docker daemon socket'));
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
