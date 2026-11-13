<?php

namespace Tapomix\Castor\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Service\isComposeFileDefined;

class UpdateTest extends TestCase
{
    public function testComposeFileFromTheEnvironment(): void
    {
        $this->assertTrue(isComposeFileDefined(['COMPOSE_FILE' => 'compose.yaml:compose.prod.yaml'], ''));
    }

    public function testComposeFileFromTheDotEnv(): void
    {
        $dotEnv = "APP_ENV=prod\nCOMPOSE_FILE=compose.yaml:compose.\${APP_ENV}.yaml # files loaded by `docker compose`\n";

        $this->assertTrue(isComposeFileDefined([], $dotEnv));
    }

    public function testComposeFileMissing(): void
    {
        $this->assertFalse(isComposeFileDefined([], "APP_ENV=prod\n"));
    }

    public function testComposeFileEmpty(): void
    {
        $this->assertFalse(isComposeFileDefined([], "COMPOSE_FILE=\n"));
    }

    public function testComposeFileCommentedOut(): void
    {
        $this->assertFalse(isComposeFileDefined([], "# COMPOSE_FILE=compose.yaml:compose.prod.yaml\n"));
    }
}
