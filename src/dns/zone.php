<?php

namespace Tapomix\Castor\Dns;

use Castor\Attribute\AsTask;
use Tapomix\Castor\Enums\DNSZoneContext;

use function Castor\fs;
use function Castor\io;

#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, description: 'Sign a DNS zone with DNSSEC', aliases: ['zone:sign'])]
function sign(string $zone): void
{
    if (!isValidZoneName($zone)) {
        throw new \InvalidArgumentException('Invalid zone name: ' . $zone);
    }

    io()->section('DNSSEC Signature for zone: ' . $zone);

    // find the raw zone file
    $rawFile = getZoneFile($zone);

    // compute the next serial number
    $nextSerial = buildNextSerial($zone);

    // replace serial in raw zone
    $content = fs()->readFile($rawFile->getPathname());
    $contentWithSerial = \str_replace(SERIAL_PLACEHOLDER, (string) $nextSerial, $content);

    // write zone with serial in a tmp file
    fs()->dumpFile(buildPathToZoneFile($zone, DNSZoneContext::Unsigned), $contentWithSerial);

    // check if unsigned zone syntax is valid before signin
    check($zone, true);

    io()->success('Unsigned zone created + checked');

    // sign file
    $process = dns_run(
        command: [
            'dnssec-signzone',
            '-S', // smart-sign = auto find keys
            '-N', 'keep', // keep current serial (we already set it)
            '-d', '/tmp', // skip generation of dsset
            '-o', $zone, // zone name
            '-K', PATH_TO_KEYS, // path to keys
            '-f', buildPathToZoneFile($zone, DNSZoneContext::Signed, true), // signed file output
            buildPathToZoneFile($zone, DNSZoneContext::Unsigned, true),
        ],
        workdir: buildPathToZones(DNSZoneContext::Signed, true)
    );

    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to sign zone file :' . $process->getErrorOutput());
    }

    // verify the final signed file
    verify($zone, true);

    io()->success('Zone signed + verified');
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, description: 'Check zone syntax with named-checkzone', aliases: ['zone:check'])]
function check(string $zone, bool $noIO = false): void
{
    if (!isValidZoneName($zone)) {
        throw new \InvalidArgumentException('Invalid zone name: ' . $zone);
    }

    $context = DNSZoneContext::Unsigned;

    if (!fs()->exists(buildPathToZoneFile($zone, $context))) {
        io()->warning('Unsigned zone file not found for zone: ' . $zone);

        return;
    }

    $process = dns_run([
        'named-checkzone',
        $zone,
        buildPathToZoneFile($zone, $context, true),
    ]);

    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to check zone: ' . $zone);
    }

    if ($noIO) { // skip success message
        return;
    }

    io()->success('Syntax OK for zone: ' . $zone);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, description: 'Verify zone signatures with dnssec-verify', aliases: ['zone:verify'])]
function verify(string $zone, bool $noIO = false): void
{
    if (!isValidZoneName($zone)) {
        throw new \InvalidArgumentException('Invalid zone name: ' . $zone);
    }

    $context = DNSZoneContext::Signed;

    if (!fs()->exists(buildPathToZoneFile($zone, $context))) {
        io()->warning('Signed zone file not found for zone: ' . $zone);

        return;
    }

    $process = dns_run([
        'dnssec-verify',
        '-o',
        $zone,
        buildPathToZoneFile($zone, $context, true),
    ]);

    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to verify zone: ' . $zone);
    }

    if ($noIO) { // skip success message
        return;
    }

    io()->success('Verification DNSSEC OK for zone: ' . $zone);
}
