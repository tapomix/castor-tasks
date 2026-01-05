<?php

namespace Tapomix\Castor\Dns;

use Castor\Helper\PathHelper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Tapomix\Castor\Enums\DNSSecAlgorithm;
use Tapomix\Castor\Enums\DNSSecFlag;
use Tapomix\Castor\Enums\DNSZoneContext;

use function Castor\context;
use function Castor\finder;
use function Castor\io;
use function Castor\run;

define('TAPOMIX_NAMESPACE_DNS', 'tapomix-dns');

define('SERIAL_PLACEHOLDER', '__SERIAL__');
define('ZONE_FILE_EXTENSION', '.zone');

// * paths inside the container
// @see volumes in compose.yaml
define('PATH_TO_KEYS', '/data/keys');
define('PATH_TO_ZONES', '/data/zones');

/** @param string[] $command */
function dns_run(array $command = [], string $workdir = '/data', string $service = 'tools'): Process
{
    // TODO ? : handle optional compose.overrides.yml
    return run(
        command: [
            'docker',
            'compose',
            'run',
            '--rm',
            '-w',
            $workdir,
            $service,
            ...$command,
        ],
        context: context()->withQuiet(true),
    );
}

function isValidZoneName(string $zone): bool
{
    return
        \str_contains($zone, '.')
        && (false !== \filter_var($zone, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
    ;
}

function findZoneKeys(string $zone, ?DNSSecAlgorithm $algorithm = null): Finder
{
    // syntax: K<zone>.+<algo>+<keytag>.key
    // example: Kexample.com.+013+12345.key
    $pattern = \sprintf('K%s.*.key', $zone); // only the zone
    if ($algorithm instanceof DNSSecAlgorithm) {
        $pattern = \sprintf('K%s.+%03d+*.key', $zone, $algorithm->value); // zone + algo
    }

    return finder()->files()->name($pattern)->in(PathHelper::getRoot() . '/.docker/server/keys');
}

function findZoneFile(string $zone, DNSZoneContext $context = DNSZoneContext::Raw): Finder
{
    return finder()
        ->files() // search only on files
        ->name(buildZoneFile($zone))
        ->in(buildPathToZones($context)) // context = sub-directory
    ;
}

function getZoneFile(string $zone, DNSZoneContext $context = DNSZoneContext::Raw): \SplFileInfo
{
    return getFirstItemFinder(findZoneFile($zone, $context));
}

function getFirstItemFinder(Finder $finder): \SplFileInfo
{
    if (!$finder->hasResults()) {
        throw new \RuntimeException('No results found');
    }

    $iterator = $finder->getIterator();
    $iterator->rewind();

    return $iterator->current();
}

function buildPathToZones(DNSZoneContext $context, bool $inContainer = false): string
{
    return $inContainer
        ? PATH_TO_ZONES . '/' . $context->value // path in container
        : PathHelper::getRoot() . '/.docker/server/zones/' . $context->value // local path
    ;
}

function buildZoneFile(string $zone): string
{
    return $zone . ZONE_FILE_EXTENSION;
}

function buildPathToZoneFile(string $zone, DNSZoneContext $context, bool $inContainer = false): string
{
    return buildPathToZones($context, $inContainer) . '/' . buildZoneFile($zone);
}

/** @return array{flag: DNSSecFlag, algo: DNSSecAlgorithm, key: string} */
function extractKeyData(string $keyFileName): array
{
    // load key file content
    $lines = loadFileInArray($keyFileName);

    // find the line that contains the DNSKEY record, skip commented lines with [^;]
    $dnskeyLine = \current(\preg_grep('/^[^;].*DNSKEY/', $lines) ?: []);

    // syntax: <zone> <?TTL> <CLASS> DNSKEY <flags> <protocol> <algorithm> <public_key_base64>
    // example: example.com. IN DNSKEY 257 3 13 aaabbbcccdddeeefffggghhhiiijjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzz+1234567==
    \preg_match('/DNSKEY\s+(\d+)\s+\d+\s+(\d+)\s+(.+)/', (string) $dnskeyLine, $matches);

    return [
        'flag' => DNSSecFlag::from((int) $matches[1]),
        'algo' => DNSSecAlgorithm::from((int) $matches[2]),
        'key' => \str_replace(' ', '', $matches[3]), // skip spaces added for more readability with long keys
    ];
}

function extractKeyTag(string $keyFileName): string
{
    \preg_match('/\+(\d+)\+(\d+)\.key$/', \basename($keyFileName), $matches);

    return $matches[2] ?? '';
}

function extractCurrentSerial(string $zoneFileName): int
{
    // load zone file content
    $lines = loadFileInArray($zoneFileName);

    // format: "<serial> ; serial" on a specific line
    // example: "2025123002 ; serial"
    $serialLine = \current(\preg_grep('/\d{10}\s*;\s*serial/i', $lines) ?: []);

    // extract current serial from line
    \preg_match('/(\d{10})/', (string) $serialLine, $matches);

    return (int) ($matches[1] ?? 0);
}

function buildNextSerial(string $zone): int
{
    // look for signed zone file to get current serial
    // for the first time, there may be no signed file yet
    $zoneFinder = findZoneFile($zone, DNSZoneContext::Signed);

    $currentSerial = $zoneFinder->hasResults()
        // got a file ... load the content and extract current serial
        ? $currentSerial = extractCurrentSerial(getFirstItemFinder($zoneFinder)->getPathname())
        // no signed file yet ... start from 0
        : 0
    ;

    $nextSerial = computeNextSerial($currentSerial);

    io()->text(\sprintf('Serial: %s --> %s', $currentSerial, $nextSerial));

    return $nextSerial;
}

/**
 * Compute the next serial number based on the current one.
 *
 * Format used: YYYYMMDDVV (VV = version for the day : [01-99])
 */
function computeNextSerial(int $currentSerial): int
{
    $today = \date('Ymd');
    $serialVersion = 0;

    if ($currentSerial > 0) {
        $serialDate = \substr((string) $currentSerial, 0, 8);

        // no need to handle $serialDate < $today
        // we can keep version at 0 and create a new serial for today with version 0+1

        if ($serialDate === $today) { // same day => save the actual version to increment
            $serialVersion = (int) \substr((string) $currentSerial, 8, 2);
        }

        if ($serialDate > $today) {
            throw new \RuntimeException('Current serial number is in the future: ' . $currentSerial);
        }

        if (99 === $serialVersion) { // need to +1 after while keeping 2 digits
            throw new \RuntimeException('Next version will exceed maximum of 99 for today: ' . $currentSerial);
        }
    }

    // create new serial (= last + 1)
    $nextSerial = $today . \sprintf('%02d', $serialVersion + 1);

    return (int) $nextSerial;
}

/** @return string[] */
function loadFileInArray(string $fileName): array
{
    // skip the warning with @ and handle it manually after
    $lines = @\file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (false === $lines) {
        throw new \RuntimeException('Unable to load file: ' . $fileName);
    }

    return $lines;
}
