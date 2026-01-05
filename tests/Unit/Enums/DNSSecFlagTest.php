<?php

namespace Tapomix\Castor\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tapomix\Castor\Enums\DNSSecFlag;

class DNSSecFlagTest extends TestCase
{
    // ========== Values Tests (RFC 4034) ==========

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc4034#section-2.1.1
     */
    public function testKskHasCorrectValue(): void
    {
        // KSK = Zone Key (256) + SEP (1) = 257
        $this->assertSame(257, DNSSecFlag::KSK->value);
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc4034#section-2.1.1
     */
    public function testZskHasCorrectValue(): void
    {
        // ZSK = Zone Key (256) only
        $this->assertSame(256, DNSSecFlag::ZSK->value);
    }

    // ========== Cases Tests ==========

    public function testEnumHasExactlyTwoCases(): void
    {
        $this->assertCount(2, DNSSecFlag::cases());
    }

    public function testAllCasesAreBackedByIntegers(): void
    {
        foreach (DNSSecFlag::cases() as $case) {
            $this->assertIsInt($case->value);
        }
    }

    public function testCasesContainsBothKeyTypes(): void
    {
        $names = array_map(fn (DNSSecFlag $flag) => $flag->name, DNSSecFlag::cases());

        $this->assertContains('KSK', $names);
        $this->assertContains('ZSK', $names);
    }

    // ========== From Tests ==========

    #[DataProvider('validFlagValuesProvider')]
    public function testFromValidValue(int $value, DNSSecFlag $expected): void
    {
        $this->assertSame($expected, DNSSecFlag::from($value));
    }

    public static function validFlagValuesProvider(): iterable
    {
        yield 'KSK (257)' => [257, DNSSecFlag::KSK];
        yield 'ZSK (256)' => [256, DNSSecFlag::ZSK];
    }

    #[DataProvider('invalidFlagValuesProvider')]
    public function testFromInvalidValueThrowsException(int $value): void
    {
        $this->expectException(\ValueError::class);

        DNSSecFlag::from($value);
    }

    public static function invalidFlagValuesProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'one (SEP only)' => [1];
        yield 'negative' => [-1];
        yield 'too high' => [999];
        yield 'close but wrong (255)' => [255];
        yield 'close but wrong (258)' => [258];
    }

    // ========== TryFrom Tests ==========

    public function testTryFromValidValueReturnsEnum(): void
    {
        $this->assertSame(DNSSecFlag::KSK, DNSSecFlag::tryFrom(257));
        $this->assertSame(DNSSecFlag::ZSK, DNSSecFlag::tryFrom(256));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(DNSSecFlag::tryFrom(0));
        $this->assertNull(DNSSecFlag::tryFrom(999));
    }

    // ========== RFC Compliance Tests ==========

    public function testKskHasSepFlagSet(): void
    {
        // SEP flag is bit 15 (value 1)
        $this->assertSame(1, DNSSecFlag::KSK->value & 1);
    }

    public function testZskDoesNotHaveSepFlagSet(): void
    {
        // ZSK should NOT have SEP flag
        $this->assertSame(0, DNSSecFlag::ZSK->value & 1);
    }

    public function testBothFlagsHaveZoneKeyFlagSet(): void
    {
        // Zone Key flag is bit 7 (value 256)
        foreach (DNSSecFlag::cases() as $flag) {
            $this->assertSame(256, $flag->value & 256, "{$flag->name} should have Zone Key flag set");
        }
    }
}
