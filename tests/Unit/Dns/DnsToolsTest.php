<?php

namespace Tapomix\Castor\Tests\Unit\Dns;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tapomix\Castor\Enums\DNSSecAlgorithm;
use Tapomix\Castor\Enums\DNSSecFlag;
use Tapomix\Castor\Enums\DNSZoneContext;

use function Tapomix\Castor\Dns\buildPathToZoneFile;
use function Tapomix\Castor\Dns\buildPathToZones;
use function Tapomix\Castor\Dns\buildZoneFile;
use function Tapomix\Castor\Dns\computeNextSerial;
use function Tapomix\Castor\Dns\extractCurrentSerial;
use function Tapomix\Castor\Dns\extractKeyData;
use function Tapomix\Castor\Dns\extractKeyTag;
use function Tapomix\Castor\Dns\isValidZoneName;
use function Tapomix\Castor\Dns\loadFileInArray;

class DnsToolsTest extends TestCase
{
    // ========== isValidZoneName Tests ==========

    #[DataProvider('validZoneNamesProvider')]
    public function testIsValidZoneNameReturnsTrueForValidNames(string $zone): void
    {
        $this->assertTrue(isValidZoneName($zone));
    }

    public static function validZoneNamesProvider(): iterable
    {
        yield 'simple domain' => ['example.com'];
        yield 'subdomain' => ['sub.example.com'];
        yield 'deep subdomain' => ['a.b.c.example.com'];
        yield 'with numbers' => ['example123.com'];
        yield 'with hyphens' => ['my-domain.com'];
        yield 'short tld' => ['example.io'];
        yield 'long tld' => ['example.technology'];
        yield 'country tld' => ['example.co.uk'];
        yield 'numeric subdomain' => ['123.example.com'];
    }

    #[DataProvider('invalidZoneNamesProvider')]
    public function testIsValidZoneNameReturnsFalseForInvalidNames(string $zone): void
    {
        $this->assertFalse(isValidZoneName($zone));
    }

    public static function invalidZoneNamesProvider(): iterable
    {
        yield 'no dot' => ['localhost'];
        yield 'empty string' => [''];
        yield 'only dot' => ['.'];
        yield 'starts with dot' => ['.example.com'];
        yield 'double dot' => ['example..com'];
        yield 'with space' => ['example .com'];
        yield 'with underscore' => ['exam_ple.com'];
        yield 'starts with hyphen' => ['-example.com'];
    }

    // ========== extractKeyTag Tests ==========

    #[DataProvider('keyFileNamesProvider')]
    public function testExtractKeyTagReturnsCorrectTag(string $filename, string $expectedTag): void
    {
        $this->assertSame($expectedTag, extractKeyTag($filename));
    }

    public static function keyFileNamesProvider(): iterable
    {
        yield 'standard key' => ['Kexample.com.+013+12345.key', '12345'];
        yield 'different algorithm' => ['Kexample.com.+008+54321.key', '54321'];
        yield 'short tag' => ['Kexample.com.+015+00001.key', '00001'];
        yield 'max tag' => ['Kexample.com.+016+65535.key', '65535'];
        yield 'subdomain zone' => ['Ksub.example.com.+013+11111.key', '11111'];
        yield 'full path' => ['/path/to/keys/Kexample.com.+013+99999.key', '99999'];
    }

    // ========== buildPathToZones Tests (container mode) ==========

    #[DataProvider('zonePathContainerProvider')]
    public function testBuildPathToZonesInContainerReturnsCorrectPath(DNSZoneContext $context, string $expectedPath): void
    {
        $this->assertSame($expectedPath, buildPathToZones($context, inContainer: true));
    }

    public static function zonePathContainerProvider(): iterable
    {
        yield 'raw context' => [DNSZoneContext::Raw, '/data/zones/raw'];
        yield 'unsigned context' => [DNSZoneContext::Unsigned, '/data/zones/unsigned'];
        yield 'signed context' => [DNSZoneContext::Signed, '/data/zones/signed'];
    }

    // ========== buildPathToZoneFile Tests (container mode) ==========

    #[DataProvider('zoneFileContainerProvider')]
    public function testBuildPathToZoneFileInContainerReturnsCorrectPath(
        string $zone,
        DNSZoneContext $context,
        string $expectedPath
    ): void {
        $this->assertSame($expectedPath, buildPathToZoneFile($zone, $context, inContainer: true));
    }

    public static function zoneFileContainerProvider(): iterable
    {
        yield 'raw zone' => ['example.com', DNSZoneContext::Raw, '/data/zones/raw/example.com.zone'];
        yield 'unsigned zone' => ['example.com', DNSZoneContext::Unsigned, '/data/zones/unsigned/example.com.zone'];
        yield 'signed zone' => ['example.com', DNSZoneContext::Signed, '/data/zones/signed/example.com.zone'];
        yield 'subdomain zone' => ['sub.example.com', DNSZoneContext::Signed, '/data/zones/signed/sub.example.com.zone'];
    }

    // ========== buildZoneFile Tests ==========

    #[DataProvider('zoneFileNameProvider')]
    public function testBuildZoneFileReturnsCorrectFileName(string $zone, string $expected): void
    {
        $this->assertSame($expected, buildZoneFile($zone));
    }

    public static function zoneFileNameProvider(): iterable
    {
        yield 'simple domain' => ['example.com', 'example.com.zone'];
        yield 'subdomain' => ['sub.example.com', 'sub.example.com.zone'];
        yield 'deep subdomain' => ['a.b.c.example.com', 'a.b.c.example.com.zone'];
    }

    // ========== extractCurrentSerial Tests ==========

    #[DataProvider('zoneSerialProvider')]
    public function testExtractCurrentSerialReturnsCorrectSerial(string $content, int $expectedSerial): void
    {
        $zoneFile = $this->createTempFile($content);

        $this->assertSame($expectedSerial, extractCurrentSerial($zoneFile));
    }

    public static function zoneSerialProvider(): iterable
    {
        yield 'standard format' => [
            <<<'ZONE'
$TTL 86400
@   IN  SOA ns1.example.com. admin.example.com. (
        2025010301 ; serial
        3600       ; refresh
        1800       ; retry
        604800     ; expire
        86400      ; minimum
)
ZONE,
            2025010301,
        ];

        yield 'no spaces around semicolon' => [
            "2024123199; serial\n@ IN SOA ns1.example.com.",
            2024123199,
        ];

        yield 'extra spaces' => [
            "    2023060515   ;   SERIAL  \n",
            2023060515,
        ];

        yield 'case insensitive' => [
            "2022010101 ; Serial\n",
            2022010101,
        ];
    }

    public function testExtractCurrentSerialReturnsZeroWhenNoSerialFound(): void
    {
        $zoneFile = $this->createTempFile("@ IN A 192.168.1.1\n");

        $this->assertSame(0, extractCurrentSerial($zoneFile));
    }

    // ========== loadFileInArray Tests ==========

    public function testLoadFileInArrayReturnsLinesWithoutNewlines(): void
    {
        $content = "line1\nline2\nline3";
        $file = $this->createTempFile($content);

        $result = loadFileInArray($file);

        $this->assertSame(['line1', 'line2', 'line3'], $result);
    }

    public function testLoadFileInArraySkipsEmptyLines(): void
    {
        $content = "line1\n\nline2\n\n\nline3";
        $file = $this->createTempFile($content);

        $result = loadFileInArray($file);

        $this->assertSame(['line1', 'line2', 'line3'], $result);
    }

    public function testLoadFileInArrayThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to load file');

        loadFileInArray('/non/existent/file.txt');
    }

    // ========== extractKeyData Tests ==========

    public function testExtractKeyDataReturnsCorrectStructure(): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::KSK,
            algorithm: DNSSecAlgorithm::ECDSAP256SHA256,
            publicKey: 'mdsswUyr3DPW132mOi8V9xESWE8jTo0dxCjjnopKl+GqJxpVXckHAeF+KkxLbxILfDLUT0rAK9iUzy1L53eKGQ=='
        );

        $result = extractKeyData($keyFile);

        $this->assertArrayHasKey('flag', $result);
        $this->assertArrayHasKey('algo', $result);
        $this->assertArrayHasKey('key', $result);
    }

    public function testExtractKeyDataReturnsCorrectFlag(): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::KSK,
            algorithm: DNSSecAlgorithm::ECDSAP256SHA256,
            publicKey: 'testkey=='
        );

        $result = extractKeyData($keyFile);

        $this->assertSame(DNSSecFlag::KSK, $result['flag']);
    }

    public function testExtractKeyDataReturnsCorrectAlgorithm(): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::ZSK,
            algorithm: DNSSecAlgorithm::ED25519,
            publicKey: 'testkey=='
        );

        $result = extractKeyData($keyFile);

        $this->assertSame(DNSSecAlgorithm::ED25519, $result['algo']);
    }

    public function testExtractKeyDataReturnsKeyWithoutSpaces(): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::KSK,
            algorithm: DNSSecAlgorithm::ECDSAP256SHA256,
            publicKey: 'aaa bbb ccc ddd==' // key with spaces for readability
        );

        $result = extractKeyData($keyFile);

        $this->assertSame('aaabbbcccddd==', $result['key']);
    }

    public function testExtractKeyDataWithZskFlag(): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::ZSK,
            algorithm: DNSSecAlgorithm::ECDSAP256SHA256,
            publicKey: 'testkey=='
        );

        $result = extractKeyData($keyFile);

        $this->assertSame(DNSSecFlag::ZSK, $result['flag']);
    }

    #[DataProvider('algorithmExtractionProvider')]
    public function testExtractKeyDataWithDifferentAlgorithms(DNSSecAlgorithm $algorithm): void
    {
        $keyFile = $this->createKeyFixture(
            zone: 'example.com',
            flag: DNSSecFlag::KSK,
            algorithm: $algorithm,
            publicKey: 'testkey=='
        );

        $result = extractKeyData($keyFile);

        $this->assertSame($algorithm, $result['algo']);
    }

    public static function algorithmExtractionProvider(): iterable
    {
        foreach (DNSSecAlgorithm::cases() as $algo) {
            yield $algo->name => [$algo];
        }
    }

    // ========== computeNextSerial Tests ==========

    public function testComputeNextSerialFromZeroReturnsFirstVersionOfToday(): void
    {
        $result = computeNextSerial(0);

        $expected = (int) (date('Ymd') . '01');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialFromPreviousDayReturnsFirstVersionOfToday(): void
    {
        // yesterday serial version 05
        $yesterday = (int) (date('Ymd', strtotime('-1 day')) . '05');

        $result = computeNextSerial($yesterday);

        $expected = (int) (date('Ymd') . '01');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialFromOldDateReturnsFirstVersionOfToday(): void
    {
        // very old serial
        $oldSerial = 2020010199;

        $result = computeNextSerial($oldSerial);

        $expected = (int) (date('Ymd') . '01');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialFromTodayIncrementsVersion(): void
    {
        // today serial version 01
        $todayVersion01 = (int) (date('Ymd') . '01');

        $result = computeNextSerial($todayVersion01);

        $expected = (int) (date('Ymd') . '02');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialFromTodayVersion50Returns51(): void
    {
        $todayVersion50 = (int) (date('Ymd') . '50');

        $result = computeNextSerial($todayVersion50);

        $expected = (int) (date('Ymd') . '51');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialFromTodayVersion98Returns99(): void
    {
        $todayVersion98 = (int) (date('Ymd') . '98');

        $result = computeNextSerial($todayVersion98);

        $expected = (int) (date('Ymd') . '99');
        $this->assertSame($expected, $result);
    }

    public function testComputeNextSerialThrowsExceptionWhenSerialInFuture(): void
    {
        $tomorrow = (int) (date('Ymd', strtotime('+1 day')) . '01');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Current serial number is in the future');

        computeNextSerial($tomorrow);
    }

    public function testComputeNextSerialThrowsExceptionWhenVersion99Reached(): void
    {
        $todayVersion99 = (int) (date('Ymd') . '99');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Next version will exceed maximum of 99 for today');

        computeNextSerial($todayVersion99);
    }

    public function testComputeNextSerialReturns10DigitNumber(): void
    {
        $result = computeNextSerial(0);

        $this->assertSame(10, strlen((string) $result));
    }

    // ========== Helper Methods ==========

    /**
     * Create a temporary file with content for testing.
     */
    private function createTempFile(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dns_test_');
        file_put_contents($tempFile, $content);

        register_shutdown_function(fn () => @unlink($tempFile));

        return $tempFile;
    }

    /**
     * Create a temporary key file fixture for testing.
     */
    private function createKeyFixture(
        string $zone,
        DNSSecFlag $flag,
        DNSSecAlgorithm $algorithm,
        string $publicKey
    ): string {
        // DNSKEY file format:
        // ; This is a comment
        // example.com. IN DNSKEY 257 3 13 <public_key_base64>
        $content = <<<EOF
; This is a zone-signing key, keyid 12345, for {$zone}.
; Created: 20250101000000 (Mon Jan  1 00:00:00 2025)
; Publish: 20250101000000 (Mon Jan  1 00:00:00 2025)
; Activate: 20250101000000 (Mon Jan  1 00:00:00 2025)
{$zone}. IN DNSKEY {$flag->value} 3 {$algorithm->value} {$publicKey}
EOF;

        $tempFile = tempnam(sys_get_temp_dir(), 'dnskey_test_');
        file_put_contents($tempFile, $content);

        // Register cleanup
        register_shutdown_function(fn () => @unlink($tempFile));

        return $tempFile;
    }
}
