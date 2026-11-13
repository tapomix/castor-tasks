<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\requiredEnvFiles;

class CheckTest extends TestCase
{
    public function testOnlyTheAppEnvFileIsRequired(): void
    {
        // the castor env file is optional, as in the default context: a Docker service has none
        $this->assertSame(['app' => '.env'], requiredEnvFiles());
    }
}
