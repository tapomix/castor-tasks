<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\appContextData;

class ContextTest extends TestCase
{
    public function testEnvironmentFallsBackOnAppEnv(): void
    {
        $data = appContextData(['APP_ENV' => 'prod']);

        $this->assertSame('prod', $data['APP.ENVIRONMENT']);
    }

    public function testAppEnvironmentOverridesAppEnv(): void
    {
        $data = appContextData(['APP_ENVIRONMENT' => 'staging', 'APP_ENV' => 'prod']);

        $this->assertSame('staging', $data['APP.ENVIRONMENT']);
    }

    public function testEnvironmentDefaultsToDev(): void
    {
        $this->assertSame('dev', appContextData([])['APP.ENVIRONMENT']);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function infraNames(): iterable
    {
        yield 'name' => ['CONTAINER_NAME', 'bank', 'APP.NAME'];
        yield 'db name' => ['DB_NAME', 'bankdb', 'APP.DB.NAME'];
        yield 'db user' => ['DB_USER', 'pg', 'APP.DB.USER'];
        yield 'server name' => ['TRAEFIK_HOST', 'bank.example.com', 'APP.SERVER_NAME'];
    }

    #[DataProvider('infraNames')]
    public function testAppVariablesFallBackOnInfraNames(string $infraName, string $value, string $contextKey): void
    {
        $this->assertSame($value, appContextData([$infraName => $value])[$contextKey]);
    }

    public function testAppVariablesOverrideInfraNames(): void
    {
        $data = appContextData(['APP_DB_NAME' => 'other', 'DB_NAME' => 'bankdb']);

        $this->assertSame('other', $data['APP.DB.NAME']);
    }
}
