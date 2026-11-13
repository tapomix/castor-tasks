<?php

namespace Tapomix\Castor\Tests\Unit\Traefik;

use Castor\Exception\ProblemException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Castor\yaml_parse;
use function Tapomix\Castor\Traefik\dumpSites;
use function Tapomix\Castor\Traefik\parseSites;
use function Tapomix\Castor\Traefik\validateSiteName;
use function Tapomix\Castor\Traefik\withoutSite;
use function Tapomix\Castor\Traefik\withSite;

class SitesTest extends TestCase
{
    public function testValidateSiteNameAcceptsLowercaseDigitsAndDashes(): void
    {
        validateSiteName('bank');
        validateSiteName('site-2');

        $this->addToAssertionCount(2);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Bank'];
        yield 'underscore' => ['my_site'];
        yield 'dot' => ['a.b'];
        yield 'space' => ['a b'];
    }

    #[DataProvider('invalidNames')]
    public function testValidateSiteNameRejectsInvalidNames(string $site): void
    {
        $this->expectException(ProblemException::class);

        validateSiteName($site);
    }

    public function testWithSiteAddsAndSorts(): void
    {
        $this->assertSame(['bank', 'coaching', 'timing'], withSite(['timing', 'bank'], 'coaching'));
    }

    public function testWithSiteRejectsDuplicate(): void
    {
        $this->expectException(ProblemException::class);

        withSite(['bank'], 'bank');
    }

    public function testWithSiteRejectsInvalidName(): void
    {
        $this->expectException(ProblemException::class);

        withSite([], 'Bank');
    }

    public function testWithoutSiteRemoves(): void
    {
        $this->assertSame(['timing'], withoutSite(['bank', 'timing'], 'bank'));
    }

    public function testWithoutSiteRejectsUnknown(): void
    {
        $this->expectException(ProblemException::class);

        withoutSite(['bank'], 'timing');
    }

    public function testParseSitesOfEmptyContent(): void
    {
        $this->assertSame([], parseSites(''));
    }

    public function testDumpSitesStartsWithHeader(): void
    {
        $this->assertStringStartsWith('#', dumpSites(['bank']));
    }

    public function testDumpSitesStructure(): void
    {
        $expected = [
            'services' => [
                'traefik' => [
                    'networks' => [
                        'bank-net' => ['aliases' => ['puppeteer']],
                    ],
                ],
            ],
            'networks' => [
                'bank-net' => ['internal' => true, 'name' => 'traefik-bank'],
            ],
        ];

        $this->assertSame($expected, yaml_parse(dumpSites(['bank'])));
    }

    public function testDumpThenParseRoundTrip(): void
    {
        $this->assertSame(['bank', 'timing'], parseSites(dumpSites(['timing', 'bank'])));
    }

    public function testDumpWithoutSitesKeepsMaps(): void
    {
        $yaml = dumpSites([]);

        $this->assertStringNotContainsString('[]', $yaml);
        $this->assertSame(
            ['services' => ['traefik' => ['networks' => []]], 'networks' => []],
            yaml_parse($yaml),
        );
        $this->assertSame([], parseSites($yaml));
    }

    public function testDumpUsesBlockStyle(): void
    {
        $this->assertStringContainsString("\n          - puppeteer\n", dumpSites(['bank']));
    }
}
