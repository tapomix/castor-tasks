<?php

namespace Tapomix\Castor\Qa\Analyzer;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

use function Castor\fs;
use function Castor\io;
use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;
use function Tapomix\Castor\Qa\buildLocalPath;

/** @param string[] $cmd */
function analyze(array $cmd): Process
{
    return docker_exec((string) variable('DOCKER.SERVICES.PHP'), $cmd);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Lint Twig templates', aliases: ['lint'], enabled: EXPR_FRAMEWORK_SYMFONY . ' && ' . EXPR_ENV_DEV)]
function lint(): ?Process
{
    if (!fs()->exists(buildLocalPath('vendor/symfony/twig-bundle'))) {
        io()->warning('Twig bundle not found');

        return null;
    }

    io()->title('Running Twig Linter');

    return analyze(['php', 'bin/console', 'lint:twig', '--show-deprecations', 'templates/']);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run PHP-CS-Fixer', aliases: ['php-cs', 'cs'], enabled: EXPR_ENV_DEV)]
function phpCsFixer(
    #[AsOption(shortcut: 'f', description: 'Really fix issues', mode: InputOption::VALUE_NEGATABLE)]
    bool $fix = false,
): ?Process {
    $binary = 'vendor/bin/php-cs-fixer';

    if (!fs()->exists(buildLocalPath($binary))) {
        io()->warning('Binary php-cs-fixer not found');

        return null;
    }

    $cmd = [$binary, 'fix'];
    if (!$fix) {
        $cmd = \array_merge($cmd, ['--dry-run', '-vv', '--diff', '--show-progress=dots']);
    }

    io()->title('Running PHP-CS-Fixer' . ($fix ? '' : ' (**dry-run**)'));

    return analyze($cmd);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run PHPStan', aliases: ['phpstan'], enabled: EXPR_ENV_DEV)]
function phpstan(): ?Process
{
    $binary = 'vendor/bin/phpstan';

    if (!fs()->exists(buildLocalPath($binary))) {
        io()->warning('Binary phpstan not found');

        return null;
    }

    io()->title('Running PHPStan (**dry-run**)');

    return analyze([$binary, 'analyse', '--memory-limit', '256M']);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run Pint', aliases: ['pint'], enabled: EXPR_FRAMEWORK_LARAVEL . ' && ' . EXPR_ENV_DEV)]
function pint(
    #[AsOption(shortcut: 'f', description: 'Really fix issues', mode: InputOption::VALUE_NEGATABLE)]
    bool $fix = false,
): ?Process {
    $binary = 'vendor/bin/pint';

    if (!fs()->exists(buildLocalPath($binary))) {
        io()->warning('Binary pint not found');

        return null;
    }

    $cmd = [$binary, 'app/'];
    if (!$fix) {
        $cmd = \array_merge($cmd, ['--test', '-v']);
    }

    io()->title('Running Pint' . ($fix ? '' : ' (**dry-run**)'));

    return analyze($cmd);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run Rector', aliases: ['rector'], enabled: EXPR_ENV_DEV)]
function rector(
    #[AsOption(shortcut: 'f', description: 'Really fix issues', mode: InputOption::VALUE_NEGATABLE)]
    bool $fix = false,
): ?Process {
    $binary = 'vendor/bin/rector';

    if (!fs()->exists(buildLocalPath($binary))) {
        io()->warning('Binary rector not found');

        return null;
    }

    $cmd = [$binary, 'process'];
    if (!$fix) {
        $cmd = \array_merge($cmd, ['--dry-run', '--debug']);
    }

    io()->title('Running Rector' . ($fix ? '' : ' (**dry-run**)'));

    return analyze($cmd);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_QA, description: 'Run Twig-CS-Fixer', aliases: ['twig-cs'], enabled: EXPR_FRAMEWORK_SYMFONY . ' && ' . EXPR_ENV_DEV)]
function twigCsFixer(
    #[AsOption(shortcut: 'f', description: 'Really fix issues', mode: InputOption::VALUE_NEGATABLE)]
    bool $fix = false,
): ?Process {
    $binary = 'vendor/bin/twig-cs-fixer';

    if (!fs()->exists(buildLocalPath($binary))) {
        io()->warning('Binary twig-cs-fixer not found');

        return null;
    }

    $cmd = [$binary, 'lint', '--debug'];
    if ($fix) {
        $cmd[] = '--fix';
    }

    io()->title('Running Twig-CS-Fixer' . ($fix ? '' : ' (**dry-run**)'));

    return analyze($cmd);
}
