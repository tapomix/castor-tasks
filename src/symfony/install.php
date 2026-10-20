<?php

declare(strict_types=1);

namespace Tapomix\Castor\Symfony;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\fs;
use function Castor\io;
use function Castor\variable;
use function Tapomix\Castor\Composer\exec as composer_exec;
use function Tapomix\Castor\Composer\execDev as composer_dev;
use function Tapomix\Castor\Docker\exec as docker_exec;
use function Tapomix\Castor\Docker\getContainerPhpVersion;

define('TAPOMIX_NAMESPACE_SYMFONY', 'tapomix-symfony');

#[AsTask(namespace: TAPOMIX_NAMESPACE_SYMFONY, description: 'Install new Symfony project', aliases: ['symfony', 'symfony:install'], enabled: EXPR_FRAMEWORK_SYMFONY . ' && ' . EXPR_ENV_DEV)]
function install(
    #[AsOption(shortcut: 'f', description: 'Install full webapp')]
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

    // disable Docker config from recipes (project manages its own Docker infrastructure)
    composer_exec(['config', '--json', 'extra.symfony.docker', 'false']);
    // official recipes only — also prevents interactive prompts for contrib recipes
    composer_exec(['config', '--json', 'extra.symfony.allow-contrib', 'false']);
    // allow phpstan extension-installer plugin (required before requiring it non-interactively)
    composer_exec(['config', '--json', 'allow-plugins.phpstan/extension-installer', 'true']);
    // the skeleton declares Symfony's minimum PHP: align it with the container's (major.minor only)
    composer_exec(['require', '--no-interaction', \sprintf('php:>=%s', getContainerPhpVersion($servicePHP))]);

    if ($full) {
        composer_exec(['require', '--no-interaction', 'webapp']);
    }

    // install dev dependencies
    $devPackages = [
        // symfony dev packages
        'symfony/debug-bundle',
        'symfony/maker-bundle',
        'symfony/web-profiler-bundle', // toolbar
        // qa tools
        'friendsofphp/php-cs-fixer',
        'phpstan/extension-installer', // also install phpstan/phpstan
        'phpstan/phpstan-symfony',
        'phpstan/phpstan-doctrine',
        'phpstan/phpstan-phpunit',
        'rector/rector',
        'vincentlanglet/twig-cs-fixer',
    ];

    composer_dev(['require', '--no-interaction', ...$devPackages]);

    // drop recipe files superseded by the project-level configuration
    $codePath = PathHelper::getRoot() . '/' . variable('APP.CODE_PATH');

    fs()->remove([
        $codePath . '/.editorconfig', // `root = true` would shadow the project one
        $codePath . '/AGENTS.md',     // superseded by the project agent rules
        $codePath . '/CLAUDE.md',     // only imports AGENTS.md
    ]);
}
