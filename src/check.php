<?php

declare(strict_types=1);

namespace Tapomix\Castor;

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\check as castor_check;
use function Castor\fs;
use function Castor\io;
use function Tapomix\Castor\Docker\buildBaseDockerComposeCmd;

// loaded here too: a project may import this file without a preset; require_once, like castor import()
require_once __DIR__ . '/docker.php';

#[AsTask(name: 'check', description: 'Check requirements', aliases: ['check'])]
function check(): void
{
    io()->title('Checking executables');

    checkExecutables([
        // 'label' => 'executable'
        'docker' => 'docker',
        'git' => 'git',
    ]);

    io()->title('Checking env files');

    checkFiles(requiredEnvFiles());

    io()->title('Checking compose files');
    buildBaseDockerComposeCmd();
    io()->text('COMPOSE_FILE OK');

    if (!fs()->exists(PathHelper::getRoot() . '/' . TAPOMIX_CASTOR_ENV_FILE)) {
        io()->note(\sprintf('No castor file "%s": optional, the defaults apply.', TAPOMIX_CASTOR_ENV_FILE));
    }

    io()->success('Requirements OK');
}

/**
 * The castor env file is optional, as in the default context: a Docker service has none.
 *
 * @return array<string, string> label => file path
 */
function requiredEnvFiles(): array
{
    return ['app' => TAPOMIX_DOTENV_FILE];
}

/** @param array<string, string> $executables */
function checkExecutables(array $executables): void
{
    foreach ($executables as $label => $exec) {
        castor_check(
            \sprintf('%s ?', \ucfirst($label)),
            \sprintf('%s executable missing', \ucfirst($label)),
            fn (): bool => null !== new ExecutableFinder()->find($exec)
        );
    }
}

/** @param array<string, string> $files */
function checkFiles(array $files): void
{
    foreach ($files as $label => $file) {
        castor_check(
            \sprintf('%s file ?', \ucfirst($label)),
            \sprintf('%s file "%s" missing', \ucfirst($label), $file),
            fn (): bool => fs()->exists(PathHelper::getRoot() . '/' . $file)
        );
    }
}
