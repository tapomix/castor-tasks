<?php

namespace Tapomix\Castor\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Infra\branchOf;
use function Tapomix\Castor\Infra\eolState;
use function Tapomix\Castor\Infra\importmapVersionPattern;
use function Tapomix\Castor\Infra\isNewerVersion;
use function Tapomix\Castor\Infra\latestVersion;
use function Tapomix\Castor\Infra\parseComposeVersionDefaults;
use function Tapomix\Castor\Infra\parseLockedVersion;
use function Tapomix\Castor\Infra\readCurrentVersion;
use function Tapomix\Castor\Infra\readPinnedVersions;
use function Tapomix\Castor\Infra\versionStatus;

class VersionsTest extends TestCase
{
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->tempDir) {
            foreach (array_diff(scandir($this->tempDir) ?: [], ['.', '..']) as $file) {
                unlink($this->tempDir . '/' . $file);
            }
            rmdir($this->tempDir);
            $this->tempDir = null;
        }
    }

    public function testParseComposeVersionDefaultsExtractsEveryVersionVariable(): void
    {
        $content = <<<'YAML'
            services:
              php:
                build:
                  args:
                    PHP_VERSION: ${PHP_VERSION:-8.4.25}
                    TAILWIND_VERSION: ${TAILWIND_VERSION:-v4.3.3}
                    USER_ID: ${USER_ID:-1000}
              pgsql:
                image: library/postgres:${PG_VERSION:-18.6}-alpine
            YAML;

        $this->assertSame(
            ['PHP_VERSION' => '8.4.25', 'TAILWIND_VERSION' => 'v4.3.3', 'PG_VERSION' => '18.6'],
            parseComposeVersionDefaults($content),
        );
    }

    public function testParseComposeVersionDefaultsIgnoresVariablesWithoutDefault(): void
    {
        $this->assertSame([], parseComposeVersionDefaults('image: postgres:${PG_VERSION}-alpine'));
    }

    public function testReadPinnedVersionsAppliesComposeOrderThenEnvOverride(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/versions-test-' . uniqid();
        mkdir($this->tempDir);

        file_put_contents($this->tempDir . '/compose.yaml', 'image: postgres:${PG_VERSION:-18}-alpine');
        file_put_contents($this->tempDir . '/compose.app.yaml', "PHP_VERSION: \${PHP_VERSION:-8.4.25}\nimage: postgres:\${PG_VERSION:-18.6}-alpine");
        file_put_contents($this->tempDir . '/.env.docker', "PHP_VERSION=8.4.20\n# PG_VERSION=17\nOTHER_VERSION=1.0\n");

        $this->assertSame(
            ['PG_VERSION' => '18.6', 'PHP_VERSION' => '8.4.20'],
            readPinnedVersions($this->tempDir),
        );
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function branchProvider(): iterable
    {
        yield 'php patch' => ['8.4.25', 2, '8.4'];
        yield 'postgres minor' => ['18.6', 1, '18'];
        yield 'tag prefix' => ['v4.3.3', 2, '4.3'];
        yield 'shorter than depth' => ['8.4', 2, '8.4'];
    }

    #[DataProvider('branchProvider')]
    public function testBranchOf(string $version, int $depth, string $expected): void
    {
        $this->assertSame($expected, branchOf($version, $depth));
    }

    public function testIsNewerVersionIgnoresTagPrefix(): void
    {
        $this->assertTrue(isNewerVersion('v4.3.10', '4.3.9'));
        $this->assertFalse(isNewerVersion('8.4.25', '8.4.25'));
        $this->assertFalse(isNewerVersion('18.5', '18.6'));
    }

    public function testLatestVersionSkipsPreReleases(): void
    {
        $this->assertSame('v4.3.3', latestVersion(['v4.3.3', 'v4.4.0-beta.1', 'v4.2.9', 'nightly']));
    }

    public function testLatestVersionRestrictsToBranch(): void
    {
        $tags = ['v5.8.1', 'v5.7.44', 'v5.7.9', 'v5.6.2'];

        $this->assertSame('v5.7.44', latestVersion($tags, '5.7', 2));
        $this->assertNull(latestVersion($tags, '4.12', 2));
    }

    public function testVersionStatusFlagsAvailableUpdate(): void
    {
        $lookup = ['inBranch' => '8.4.27', 'latest' => '8.5.10', 'eol' => '2099-12-31', 'pending' => false];

        [$label, $actionable] = versionStatus('8.4.25', $lookup);

        $this->assertStringContainsString('update available', $label);
        $this->assertTrue($actionable);
    }

    public function testVersionStatusDoesNotActOnPendingImage(): void
    {
        $lookup = ['inBranch' => '8.4.27', 'latest' => '8.4.27', 'eol' => null, 'pending' => true];

        [$label, $actionable] = versionStatus('8.4.25', $lookup);

        $this->assertStringContainsString('pending', $label);
        $this->assertFalse($actionable);
    }

    public function testVersionStatusFlagsEndOfLifeBranch(): void
    {
        $lookup = ['inBranch' => '8.1.34', 'latest' => '8.5.10', 'eol' => '2025-12-31', 'pending' => false];

        [$label, $actionable] = versionStatus('8.1.34', $lookup);

        $this->assertStringContainsString('EOL', $label);
        $this->assertTrue($actionable);
    }

    public function testVersionStatusUpToDate(): void
    {
        $lookup = ['inBranch' => '18.6', 'latest' => '18.6', 'eol' => '2030-11-14', 'pending' => false];

        [$label, $actionable] = versionStatus('18.6', $lookup);

        $this->assertStringContainsString('up to date', $label);
        $this->assertFalse($actionable);
    }

    public function testParseLockedVersionReturnsInstalledVersionWithoutPrefix(): void
    {
        $lock = (string) json_encode(['packages' => [
            ['name' => 'symfony/console', 'version' => 'v8.1.6'],
            ['name' => 'symfony/framework-bundle', 'version' => 'v8.1.7'],
        ]]);

        $this->assertSame('8.1.7', parseLockedVersion($lock, 'symfony/framework-bundle'));
        $this->assertNull(parseLockedVersion($lock, 'laravel/framework'));
    }

    public function testParseLockedVersionToleratesInvalidLock(): void
    {
        $this->assertNull(parseLockedVersion('not json', 'symfony/framework-bundle'));
    }

    public function testReadCurrentVersionFromComposerLock(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/versions-test-' . uniqid();
        mkdir($this->tempDir);

        file_put_contents($this->tempDir . '/composer.lock', (string) json_encode(['packages' => [
            ['name' => 'laravel/framework', 'version' => 'v12.28.1'],
        ]]));

        $this->assertSame('12.28.1', readCurrentVersion(['file' => 'composer.lock', 'package' => 'laravel/framework'], [], $this->tempDir));
        $this->assertNull(readCurrentVersion(['file' => 'missing.lock', 'package' => 'laravel/framework'], [], $this->tempDir));
    }

    public function testReadCurrentVersionFromImportmap(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/versions-test-' . uniqid();
        mkdir($this->tempDir);

        file_put_contents($this->tempDir . '/importmap.php', <<<'PHP'
            <?php
            return [
                'alpinejs-extra' => [
                    'version' => '9.9.9',
                ],
                'alpinejs' => [
                    'type' => 'js',
                    'version' => '3.15.9',
                ],
            ];
            PHP);

        $alpine = ['file' => 'importmap.php', 'pattern' => importmapVersionPattern('alpinejs')];

        $this->assertSame('3.15.9', readCurrentVersion($alpine, [], $this->tempDir));

        file_put_contents($this->tempDir . '/importmap.php', "<?php\nreturn ['chart.js' => ['version' => '4.5.1']];\n");
        $this->assertNull(readCurrentVersion($alpine, [], $this->tempDir));
    }

    public function testEolState(): void
    {
        $this->assertNull(eolState(null));
        $this->assertSame('past', eolState('2020-01-31'));
        $this->assertSame('soon', eolState(date('Y-m-d', (int) strtotime('+30 days'))));
        $this->assertNull(eolState(date('Y-m-d', (int) strtotime('+200 days'))));
    }

    public function testVersionStatusAnnouncesCloseEndOfLife(): void
    {
        $eol = date('Y-m-d', (int) strtotime('+30 days'));
        $lookup = ['inBranch' => '8.1.7', 'latest' => '8.1.7', 'eol' => $eol, 'pending' => false];

        [$label, $actionable] = versionStatus('8.1.7', $lookup);

        $this->assertStringContainsString('EOL soon', $label);
        $this->assertFalse($actionable);
    }

    public function testVersionStatusFlagsFailedLookup(): void
    {
        [$label, $actionable] = versionStatus('18.6', null);

        $this->assertStringContainsString('lookup failed', $label);
        $this->assertTrue($actionable);
    }
}
