<?php

namespace Tapomix\Castor\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tapomix\Castor\Enums\DNSZoneContext;

class DNSZoneContextTest extends TestCase
{
    // ========== Values Tests ==========

    public function testRawHasCorrectValue(): void
    {
        $this->assertSame('raw', DNSZoneContext::Raw->value);
    }

    public function testUnsignedHasCorrectValue(): void
    {
        $this->assertSame('unsigned', DNSZoneContext::Unsigned->value);
    }

    public function testSignedHasCorrectValue(): void
    {
        $this->assertSame('signed', DNSZoneContext::Signed->value);
    }

    // ========== Cases Tests ==========

    public function testEnumHasExactlyThreeCases(): void
    {
        $this->assertCount(3, DNSZoneContext::cases());
    }

    public function testAllCasesAreBackedByStrings(): void
    {
        foreach (DNSZoneContext::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    public function testAllValuesAreLowercase(): void
    {
        foreach (DNSZoneContext::cases() as $case) {
            $this->assertSame(strtolower($case->value), $case->value, "{$case->name} value should be lowercase");
        }
    }

    // ========== From Tests ==========

    #[DataProvider('validContextValuesProvider')]
    public function testFromValidValue(string $value, DNSZoneContext $expected): void
    {
        $this->assertSame($expected, DNSZoneContext::from($value));
    }

    public static function validContextValuesProvider(): iterable
    {
        yield 'raw' => ['raw', DNSZoneContext::Raw];
        yield 'unsigned' => ['unsigned', DNSZoneContext::Unsigned];
        yield 'signed' => ['signed', DNSZoneContext::Signed];
    }

    #[DataProvider('invalidContextValuesProvider')]
    public function testFromInvalidValueThrowsException(string $value): void
    {
        $this->expectException(\ValueError::class);

        DNSZoneContext::from($value);
    }

    public static function invalidContextValuesProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'uppercase RAW' => ['RAW'];
        yield 'uppercase UNSIGNED' => ['UNSIGNED'];
        yield 'uppercase SIGNED' => ['SIGNED'];
        yield 'unknown value' => ['unknown'];
        yield 'partial match' => ['sign'];
    }

    // ========== TryFrom Tests ==========

    public function testTryFromValidValueReturnsEnum(): void
    {
        $this->assertSame(DNSZoneContext::Raw, DNSZoneContext::tryFrom('raw'));
        $this->assertSame(DNSZoneContext::Unsigned, DNSZoneContext::tryFrom('unsigned'));
        $this->assertSame(DNSZoneContext::Signed, DNSZoneContext::tryFrom('signed'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(DNSZoneContext::tryFrom(''));
        $this->assertNull(DNSZoneContext::tryFrom('invalid'));
    }

    public function testTryFromIsCaseSensitive(): void
    {
        $this->assertNull(DNSZoneContext::tryFrom('Raw'));
        $this->assertNull(DNSZoneContext::tryFrom('RAW'));
    }

    // ========== Workflow Order Tests ==========

    /**
     * Test that contexts represent the signing workflow order:
     * Raw -> Unsigned -> Signed
     */
    public function testContextsRepresentWorkflowOrder(): void
    {
        $cases = DNSZoneContext::cases();
        $values = array_map(fn (DNSZoneContext $c) => $c->value, $cases);

        // Verify the expected workflow order
        $this->assertSame(['raw', 'unsigned', 'signed'], $values);
    }

    // ========== Values Can Be Used As Directory Names ==========

    public function testValuesAreValidDirectoryNames(): void
    {
        foreach (DNSZoneContext::cases() as $case) {
            // Should not contain path separators or special characters
            $this->assertMatchesRegularExpression('/^[a-z]+$/', $case->value);
        }
    }
}
