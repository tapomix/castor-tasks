<?php

namespace Tapomix\Castor\Tests\Unit\Vps;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Vps\ensureComposerAuthFile;

class DeployTest extends TestCase
{
    private ?string $root = null;

    protected function tearDown(): void
    {
        if (null !== $this->root) {
            @unlink($this->root . '/.docker/.composer-auth.json');
            rmdir($this->root . '/.docker');
            rmdir($this->root);
            $this->root = null;
        }
    }

    public function testComposerAuthFileIsCreatedWhenAbsent(): void
    {
        $root = $this->tempRoot();
        ensureComposerAuthFile($root);

        $this->assertSame('{}', file_get_contents($root . '/.docker/.composer-auth.json'));
    }

    public function testComposerAuthFileIsNeverOverwritten(): void
    {
        $root = $this->tempRoot();
        file_put_contents($root . '/.docker/.composer-auth.json', '{"github-oauth": {}}');
        ensureComposerAuthFile($root);

        $this->assertSame('{"github-oauth": {}}', file_get_contents($root . '/.docker/.composer-auth.json'));
    }

    private function tempRoot(): string
    {
        $this->root = sys_get_temp_dir() . '/deploy-test-' . uniqid();
        mkdir($this->root . '/.docker', 0o777, true);

        return $this->root;
    }
}
