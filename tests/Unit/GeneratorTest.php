<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Tools\generatePassword;
use function Tapomix\Castor\Tools\generateToken;

class GeneratorTest extends TestCase
{
    // ========== Password Tests ==========

    public function testGeneratePasswordReturnsString(): void
    {
        $password = generatePassword();

        $this->assertIsString($password);
    }

    public function testGeneratePasswordDefaultLength(): void
    {
        $password = generatePassword();

        $this->assertSame(16, strlen($password));
    }

    public function testGeneratePasswordCustomLength(): void
    {
        $password = generatePassword(20);

        $this->assertSame(20, strlen($password));
    }

    public function testGeneratePasswordMinimumLength(): void
    {
        // Should enforce minimum of 12 even if requested less
        $password = generatePassword(8);

        $this->assertSame(12, strlen($password));
    }

    public function testGeneratePasswordContainsLowercase(): void
    {
        $password = generatePassword();

        $this->assertMatchesRegularExpression('/[a-z]/', $password);
    }

    public function testGeneratePasswordContainsUppercase(): void
    {
        $password = generatePassword();

        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
    }

    public function testGeneratePasswordContainsNumber(): void
    {
        $password = generatePassword();

        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }

    public function testGeneratePasswordContainsSpecialChar(): void
    {
        $password = generatePassword();

        $this->assertMatchesRegularExpression('/[!@#$%&*\-_+=?]/', $password);
    }

    public function testGeneratePasswordIsRandom(): void
    {
        $password1 = generatePassword();
        $password2 = generatePassword();

        // Very unlikely (but not impossible) that two passwords are identical
        $this->assertNotSame($password1, $password2);
    }

    public function testGeneratePasswordOnlyContainsAllowedCharacters(): void
    {
        $password = generatePassword();

        // Should only contain: a-z, A-Z, 0-9, and special chars !@#$%&*-_+=?
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9!@#$%&*\-_+=?]+$/', $password);
    }

    // ========== Token Tests ==========

    public function testGenerateTokenReturnsString(): void
    {
        $token = generateToken();

        $this->assertIsString($token);
    }

    public function testGenerateTokenDefaultLength(): void
    {
        $token = generateToken();

        // bin2hex doubles the length (1 byte = 2 hex chars)
        $this->assertSame(64, strlen($token));
    }

    public function testGenerateTokenCustomLength(): void
    {
        $token = generateToken(16);

        // bin2hex: 16 bytes = 32 hex chars
        $this->assertSame(32, strlen($token));
    }

    public function testGenerateTokenMinimumLength(): void
    {
        // Should enforce minimum of 1 byte = 2 hex chars
        $token = generateToken(0);

        $this->assertSame(2, strlen($token));
    }

    public function testGenerateTokenOnlyContainsHexCharacters(): void
    {
        $token = generateToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testGenerateTokenIsRandom(): void
    {
        $token1 = generateToken();
        $token2 = generateToken();

        // Very unlikely (but not impossible) that two tokens are identical
        $this->assertNotSame($token1, $token2);
    }

    public function testGenerateTokenWithLength1(): void
    {
        $token = generateToken(1);

        $this->assertSame(2, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{2}$/', $token);
    }

    public function testGenerateTokenWithLength100(): void
    {
        $token = generateToken(100);

        $this->assertSame(200, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{200}$/', $token);
    }
}
