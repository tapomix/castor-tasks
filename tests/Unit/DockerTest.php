<?php

namespace Tapomix\Castor\Tests\Unit;

use Castor\Exception\ProblemException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Docker\siteComposeFile;
use function Tapomix\Castor\Docker\validateComposeFile;

class DockerTest extends TestCase
{
    public function testSiteComposeFile(): void
    {
        $this->assertSame('compose.yaml:compose.prod.yaml:compose.app.yaml:compose.override.yaml', siteComposeFile('prod'));
    }

    public function testSiteWithTheExactLinePasses(): void
    {
        validateComposeFile(siteComposeFile('prod'), 'prod', 'prod', true);

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, string}> */
    public static function wrongSiteLines(): iterable
    {
        yield 'override missing' => ['compose.yaml:compose.prod.yaml:compose.app.yaml', 'prod'];
        yield 'app missing' => ['compose.yaml:compose.prod.yaml:compose.override.yaml', 'prod'];
        yield 'wrong order' => ['compose.yaml:compose.app.yaml:compose.prod.yaml:compose.override.yaml', 'prod'];
        yield 'dev file in prod' => ['compose.yaml:compose.dev.yaml:compose.app.yaml:compose.override.yaml', 'prod'];
        yield 'APP_ENV missing from .env' => ['compose.yaml:compose..yaml:compose.app.yaml:compose.override.yaml', 'prod'];
        yield 'profiles missing' => ['compose.yaml:compose.prod.yaml:compose.app.yaml:compose.override.yaml', ''];
        yield 'profiles of another env' => ['compose.yaml:compose.prod.yaml:compose.app.yaml:compose.override.yaml', 'dev'];
        yield 'nothing set' => ['', ''];
    }

    #[DataProvider('wrongSiteLines')]
    public function testSiteWithAWrongLineIsRefused(string $composeFile, string $profiles): void
    {
        $this->expectException(ProblemException::class);

        validateComposeFile($composeFile, $profiles, 'prod', true);
    }

    public function testRefusalNamesTheExpectedLine(): void
    {
        $this->expectExceptionMessage('compose.yaml:compose.prod.yaml:compose.app.yaml:compose.override.yaml');

        validateComposeFile('compose.yaml', 'prod', 'prod', true);
    }

    public function testRefusalDoesNotBlameTheEnvFileAlone(): void
    {
        // the value may come from an exported variable, which wins over .env
        $this->expectExceptionMessage('environment or .env');

        validateComposeFile('compose.yaml', 'prod', 'prod', true);
    }

    public function testServiceAcceptsItsOwnList(): void
    {
        // Traefik in dev: no compose.sites.yaml, no profiles
        validateComposeFile('compose.yaml:compose.dev.yaml', '', 'dev', false);

        $this->addToAssertionCount(1);
    }

    public function testServiceWithoutComposeFileIsRefused(): void
    {
        $this->expectException(ProblemException::class);

        validateComposeFile('', '', 'prod', false);
    }
}
