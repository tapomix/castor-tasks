<?php

namespace Tapomix\Castor\Dns;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;
use Tapomix\Castor\Enums\DNSSecAlgorithm;
use Tapomix\Castor\Enums\DNSSecFlag;

use function Castor\fs;
use function Castor\io;

#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, name: 'generate', description: 'Generate KSK+ZSK keys', aliases: ['keys:generate'])]
function generate(
    string $zone,
    #[AsOption(name: 'algorithm', description: 'Algorithm', shortcut: 'a')]
    int $algo = DNSSecAlgorithm::ED25519->value,
): void {
    if (!isValidZoneName($zone)) {
        throw new \InvalidArgumentException('Invalid zone name: ' . $zone);
    }

    $algorithm = DNSSecAlgorithm::from($algo);
    $finder = findZoneKeys($zone, $algorithm);

    if ($finder->hasResults()) {
        io()->warning('Keys already exist for this zone+algorithm');

        return;
    }

    $zskCmd = [
        'dnssec-keygen',
        // algo
        '-a', $algorithm->name,
        // destination dir
        '-K', PATH_TO_KEYS,
        // owner type
        '-n', 'ZONE',
    ];

    $kskCmd = [...$zskCmd, '-f', DNSSecFlag::KSK->name]; // same as ZSK with -f flag

    io()->section('Keys generation');

    // KSK
    $process = dns_run([...$kskCmd, $zone]);

    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to create KSK => ' . $process->getErrorOutput());
    }

    io()->success('KSK created => ' . $process->getOutput());

    // ZSK
    $process = dns_run([...$zskCmd, $zone]);

    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to create ZSK => ' . $process->getErrorOutput());
    }

    io()->success('ZSK created => ' . $process->getOutput());

    // display keys listing to see new keys
    listing($zone, false);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DNS, description: 'List all DNSSEC keys for a zone', aliases: ['keys:list'])]
function listing(
    string $zone,
    #[AsOption(name: 'only-ksk', shortcut: 'k', description: 'List only KSK keys', mode: InputOption::VALUE_NONE)]
    bool $onlyKsk,
): void {
    if (!isValidZoneName($zone)) {
        throw new \InvalidArgumentException('Invalid zone name: ' . $zone);
    }

    $finder = findZoneKeys($zone)->sortByName();

    if (!$finder->hasResults()) {
        io()->warning('No keys found for this zone');

        return;
    }

    $data = [];
    $kskFiles = [];
    foreach ($finder as $key) {
        $keyFile = $key->getPathname();
        $keyData = extractKeyData($keyFile);

        $isKsk = (DNSSecFlag::KSK === $keyData['flag']);

        // If onlyKsk is requested, add to table only when the key is a KSK.
        if (!$onlyKsk || $isKsk) {
            $data[] = [
                extractKeyTag($keyFile),
                $keyData['flag']->name . ' (' . $keyData['flag']->value . ')',
                $keyData['algo']->name . ' (' . $keyData['algo']->value . ')',
                fs()->exists(\str_replace('.key', '.private', $keyFile)) ? 'OK' : 'KO',
                $keyData['key'],
            ];
        }

        if ($isKsk) {
            $kskFiles[] = $key->getBasename();
        }
    }

    io()->section(($onlyKsk ? 'KSK ' : '') . 'Key(s) for zone: *' . $zone . '*');
    io()->note('Only need to import KSK with OVH');
    io()->table(
        ['Tag', 'Type', 'Algorithm', 'PK', 'Key (base64)'],
        $data,
    );

    $dsCmd = \array_map(
        fn (string $ksk): string => 'dnssec-dsfromkey -2 ' . $ksk, // -2 for SHA-256
        $kskFiles,
    );

    io()->section('DS Record(s) for registrars');

    $process = dns_run(
        // merge all commands in one call as it takes time to "run" container
        command: ['sh', '-c', \implode(' && ', $dsCmd)],
        workdir: PATH_TO_KEYS,
    );
    if (!$process->isSuccessful()) {
        throw new \RuntimeException('Fail to generate DS record');
    }

    io()->writeln($process->getOutput());
}
