<?php

namespace Tapomix\Castor\Tests\Unit;

use Castor\Exception\ProblemException;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\appContextData;
use function Tapomix\Castor\Docker\validateComposeFile;
use function Tapomix\Castor\loadEnvFiles;

/** The env files read for real, as Compose reads `.env`: one file, no default, castor file never replacing it. */
class EnvLoadingTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $env;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;
        $this->root = sys_get_temp_dir() . '/env-loading-test-' . uniqid();
        mkdir($this->root . '/.castor', 0o777, true);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
        foreach (['/.env', '/.env.local', '/.castor/.env.castor'] as $file) {
            @unlink($this->root . $file);
        }
        rmdir($this->root . '/.castor');
        rmdir($this->root);
    }

    public function testSiteWithoutAppEnvIsRefused(): void
    {
        // no APP_ENV: Compose reads compose..yaml, castor must not fall back on a full dev config
        file_put_contents($this->root . '/.env', "COMPOSE_PROFILES=\${APP_ENV}\nCOMPOSE_FILE=compose.yaml:compose.\${APP_ENV}.yaml:compose.app.yaml:compose.override.yaml\n");
        loadEnvFiles($this->root);
        $data = appContextData($_SERVER);

        $this->expectException(ProblemException::class);

        validateComposeFile((string) $data['COMPOSE.FILE'], (string) $data['COMPOSE.PROFILES'], (string) $data['APP.ENVIRONMENT'], true);
    }

    public function testRootEnvLocalIsNotRead(): void
    {
        // Compose never reads .env.local: castor must not either
        file_put_contents($this->root . '/.env', "APP_ENV=dev\n");
        file_put_contents($this->root . '/.env.local', "APP_ENV=prod\n");
        loadEnvFiles($this->root);

        $this->assertSame('dev', appContextData($_SERVER)['APP.ENVIRONMENT']);
    }

    public function testCastorFileNeverReplacesAnEnvValue(): void
    {
        file_put_contents($this->root . '/.env', "APP_ENV=prod\nCOMPOSE_FILE=compose.yaml:compose.override.yaml\n");
        file_put_contents($this->root . '/.castor/.env.castor', "APP_ENV=dev\nCOMPOSE_FILE=compose.yaml\nAPP_FRAMEWORK=symfony\n");
        loadEnvFiles($this->root);

        $this->assertSame('prod', $_SERVER['APP_ENV']);
        $this->assertSame('compose.yaml:compose.override.yaml', $_SERVER['COMPOSE_FILE']);
        $this->assertSame('symfony', $_SERVER['APP_FRAMEWORK']);
    }

    public function testExportedVariableWinsOverEnv(): void
    {
        // as for Compose: castor must validate the value Compose will use
        $_SERVER['COMPOSE_FILE'] = 'compose.yaml:compose.exported.yaml';
        file_put_contents($this->root . '/.env', "COMPOSE_FILE=compose.yaml:compose.override.yaml\n");
        loadEnvFiles($this->root);

        $this->assertSame('compose.yaml:compose.exported.yaml', appContextData($_SERVER)['COMPOSE.FILE']);
    }

    public function testCastorFileInterpolatesEnvValues(): void
    {
        file_put_contents($this->root . '/.env', "CONTAINER_NAME=bank\n");
        file_put_contents($this->root . '/.castor/.env.castor', "INFRA_REMOTE=\${CONTAINER_NAME}-infra\n");
        loadEnvFiles($this->root);

        $this->assertSame('bank-infra', $_SERVER['INFRA_REMOTE']);
    }
}
