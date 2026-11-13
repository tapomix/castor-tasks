<?php

declare(strict_types=1);

namespace Tapomix\Castor\Composer;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;

use function Castor\context;
use function Castor\variable;
use function Tapomix\Castor\Docker\exec as docker_exec;

define('TAPOMIX_NAMESPACE_COMPOSER', 'tapomix-composer');

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a composer command', aliases: ['composer'])]
function exec(
    #[AsRawTokens]
    array $args = [],
): void {
    try {
        docker_exec((string) variable('DOCKER.SERVICES.PHP'), ['composer', ...$args]);
    } finally {
        // a failing command (e.g. a `validate` with warnings) may still have written the files
        stripComposerJsonFinalNewline();
    }
}

/**
 * Composer always ends `composer.json` with a newline: drop it when the app's `.editorconfig` asks for none.
 * Run after every command (require, remove, update, bump...): a file Composer did not touch is left as is.
 */
function stripComposerJsonFinalNewline(): void
{
    $root = PathHelper::getRoot();

    if (!jsonFinalNewlineDisabled($root . '/.editorconfig')) {
        return;
    }

    $path = $root . '/' . variable('APP.CODE_PATH') . '/composer.json';

    if (!\is_file($path)) {
        return;
    }

    $content = (string) \file_get_contents($path);
    $stripped = \rtrim($content, "\n");

    if ($stripped !== $content) {
        \file_put_contents($path, $stripped);
    }
}

/**
 * Whether the `[*.json]` section of the given `.editorconfig` sets `insert_final_newline = false`.
 * Only that exact section is read: no glob matching, no parent `.editorconfig`.
 */
function jsonFinalNewlineDisabled(string $editorConfig): bool
{
    if (!\is_file($editorConfig)) {
        return false;
    }

    $inJsonSection = false;

    foreach ((array) \file($editorConfig, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) as $line) {
        $line = \trim((string) $line);

        if (1 === \preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $inJsonSection = '*.json' === $matches[1];

            continue;
        }

        if ($inJsonSection && 1 === \preg_match('/^insert_final_newline\s*=\s*false$/i', $line)) {
            return true;
        }
    }

    return false;
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a compose command for dev', aliases: ['composer:dev'])]
function execDev(
    #[AsRawTokens]
    array $args = [],
): void {
    if ([] === $args) {
        throw new ProblemException('At least one argument is required for composer:dev (e.g., "install", "update")');
    }

    $args[] = '--dev';
    exec($args);
}

/** @param string[] $args */
#[AsTask(namespace: TAPOMIX_NAMESPACE_COMPOSER, description: 'Execute a global compose command', aliases: ['composer:global'])]
function execGlobal(
    #[AsRawTokens]
    array $args = [],
): void {
    if ([] === $args) {
        throw new ProblemException('At least one argument is required for composer:global (e.g., "require vendor/package")');
    }

    exec(['global', ...$args]);
}

/** Major.minor PHP version running in the given service (e.g. `8.5`) */
function getContainerPhpVersion(string $service): string
{
    // no TTY: a TTY process streams its output to the terminal and leaves nothing to read
    $process = docker_exec(
        $service,
        ['php', '-r', 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'],
        context()->withTty(false)->withQuiet(),
    );

    $version = \trim($process->getOutput());

    if (1 !== \preg_match('/^\d+\.\d+$/', $version)) {
        throw new ProblemException(\sprintf('Unable to read the PHP version of the "%s" service.', $service));
    }

    return $version;
}
