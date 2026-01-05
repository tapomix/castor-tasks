<?php

namespace Tapomix\Castor\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tapomix\Castor\Enums\DNSSecAlgorithm;

class DNSSecAlgorithmTest extends TestCase
{
    // ========== Values Tests ==========

    public function testRsaSha256HasCorrectValue(): void
    {
        $this->assertSame(8, DNSSecAlgorithm::RSASHA256->value);
    }

    public function testRsaSha512HasCorrectValue(): void
    {
        $this->assertSame(10, DNSSecAlgorithm::RSASHA512->value);
    }

    public function testEcdsaP256Sha256HasCorrectValue(): void
    {
        $this->assertSame(13, DNSSecAlgorithm::ECDSAP256SHA256->value);
    }

    public function testEcdsaP384Sha384HasCorrectValue(): void
    {
        $this->assertSame(14, DNSSecAlgorithm::ECDSAP384SHA384->value);
    }

    public function testEd25519HasCorrectValue(): void
    {
        $this->assertSame(15, DNSSecAlgorithm::ED25519->value);
    }

    public function testEd448HasCorrectValue(): void
    {
        $this->assertSame(16, DNSSecAlgorithm::ED448->value);
    }

    // ========== Cases Tests ==========

    public function testEnumHasExactlySixCases(): void
    {
        $this->assertCount(6, DNSSecAlgorithm::cases());
    }

    public function testAllCasesAreBackedByIntegers(): void
    {
        foreach (DNSSecAlgorithm::cases() as $case) {
            $this->assertIsInt($case->value);
        }
    }

    // ========== From Tests ==========

    #[DataProvider('validAlgorithmValuesProvider')]
    public function testFromValidValue(int $value, DNSSecAlgorithm $expected): void
    {
        $this->assertSame($expected, DNSSecAlgorithm::from($value));
    }

    public static function validAlgorithmValuesProvider(): iterable
    {
        yield 'RSASHA256' => [8, DNSSecAlgorithm::RSASHA256];
        yield 'RSASHA512' => [10, DNSSecAlgorithm::RSASHA512];
        yield 'ECDSAP256SHA256' => [13, DNSSecAlgorithm::ECDSAP256SHA256];
        yield 'ECDSAP384SHA384' => [14, DNSSecAlgorithm::ECDSAP384SHA384];
        yield 'ED25519' => [15, DNSSecAlgorithm::ED25519];
        yield 'ED448' => [16, DNSSecAlgorithm::ED448];
    }

    #[DataProvider('invalidAlgorithmValuesProvider')]
    public function testFromInvalidValueThrowsException(int $value): void
    {
        $this->expectException(\ValueError::class);

        DNSSecAlgorithm::from($value);
    }

    public static function invalidAlgorithmValuesProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'deprecated RSASHA1' => [5];
        yield 'deprecated NSEC3RSASHA1' => [7];
        yield 'negative' => [-1];
        yield 'too high' => [999];
    }

    // ========== TryFrom Tests ==========

    public function testTryFromValidValueReturnsEnum(): void
    {
        $this->assertSame(DNSSecAlgorithm::ED25519, DNSSecAlgorithm::tryFrom(15));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(DNSSecAlgorithm::tryFrom(999));
    }

    public function testTryFromDeprecatedAlgorithmReturnsNull(): void
    {
        // RSASHA1 (5) and NSEC3RSASHA1 (7) are deprecated and not in enum
        $this->assertNull(DNSSecAlgorithm::tryFrom(5));
        $this->assertNull(DNSSecAlgorithm::tryFrom(7));
    }
}
