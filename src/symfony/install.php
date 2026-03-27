<?php

namespace Tapomix\Castor\Symfony;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Console\Input\InputOption;

use function Castor\fs;
use function Castor\io;
use function Castor\variable;
use function Tapomix\Castor\Composer\exec as composer_exec;
use function Tapomix\Castor\Composer\execDev as composer_dev;
use function Tapomix\Castor\Docker\exec as docker_exec;

define('TAPOMIX_NAMESPACE_SYMFONY', 'tapomix-symfony');

#[AsTask(namespace: TAPOMIX_NAMESPACE_SYMFONY, description: 'Install new Symfony project', aliases: ['symfony'], enabled: EXPR_FRAMEWORK_SYMFONY . ' && ' . EXPR_ENV_DEV)]
function install(
    #[AsOption(shortcut: 'f', mode: InputOption::VALUE_NEGATABLE, description: 'Install full webapp')]
    bool $full = false,
    #[AsOption(shortcut: 's', description: 'Symfony version constraint')]
    string $symfonyVersion = '^8.0',
): void {
    io()->title(\sprintf('Installing Symfony %s (%s)', $symfonyVersion, $full ? 'webapp' : 'skeleton'));

    $frameworkLocalPath = PathHelper::getRoot() . '/fmk';
    $frameworkContainerPath = '../fmk';

    $servicePHP = (string) variable('DOCKER.SERVICES.PHP');

    composer_exec(['create-project', \sprintf('symfony/skeleton:%s', $symfonyVersion), $frameworkContainerPath]);

    if (!fs()->exists($frameworkLocalPath)) {
        io()->warning('Symfony installation failed');

        return;
    }

    // move files to the code directory
    docker_exec($servicePHP, ['cp', '-a', $frameworkContainerPath . '/.', '.']);
    docker_exec($servicePHP, ['rm', '-r', $frameworkContainerPath]);

    if ($full) {
        composer_exec(['require', 'webapp']);
    }

    // install dev dependencies
    $devPackages = [
        // symfony dev packages
        'symfony/debug-bundle',
        'symfony/maker-bundle',
        'symfony/web-profiler-bundle', // toolbar
        // qa tools
        'carthage-software/mago',
        'friendsofphp/php-cs-fixer',
        'phpstan/extension-installer', // also install phpstan/phpstan
        'phpstan/phpstan-symfony',
        'phpstan/phpstan-doctrine',
        'phpstan/phpstan-phpunit',
        'rector/rector',
        'vincentlanglet/twig-cs-fixer',
    ];

    composer_dev(['require', ...$devPackages]);
}
