<?php

namespace Tapomix\Castor\Docker;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Console\Output\VerbosityLevel;
use Castor\Context;
use Castor\Exception\ProblemException;
use Castor\Helper\PathHelper;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run as castor_run;
use function Castor\variable;

define('TAPOMIX_NAMESPACE_DOCKER', 'tapomix-docker');

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Build all services', aliases: ['build'])]
function build(bool $noCache = false): void
{
    io()->title('Building server');

    $context = context()->withVerbosityLevel(VerbosityLevel::VERBOSE);

    $cmd = buildBaseDockerComposeCmd();
    $cmd[] = 'build';
    if ($noCache) {
        $cmd[] = '--no-cache';
    }

    castor_run($cmd, context: $context);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Pull fresh images')]
function pull(): void
{
    io()->title('Pulling images');

    castor_run([...buildBaseDockerComposeCmd(), 'pull', '--ignore-buildable']);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Start all services', aliases: ['start', 'up'])]
function start(): void
{
    io()->title('Starting server');

    castor_run([...buildBaseDockerComposeCmd(), 'up', '--detach', '--wait']);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Stop all services', aliases: ['stop', 'down'])]
function stop(): void
{
    io()->title('Stopping server');

    castor_run([...buildBaseDockerComposeCmd(), 'down', '--remove-orphans']);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Show server logs', aliases: ['logs'])]
function logs(): void
{
    io()->title('Showing server logs');

    castor_run([...buildBaseDockerComposeCmd(), 'logs', '-f']);
}

/** Note: Alias is 'shell' (not 'sh') to avoid confusion since the default shell is 'bash', not 'sh' */
#[AsTask(namespace: TAPOMIX_NAMESPACE_DOCKER, description: 'Open terminal in a container', aliases: ['shell'])]
function shell(
    string $service,
    #[AsOption(description: 'Shell to use in the container')]
    string $shell = 'bash',
): void {
    exec($service, [$shell]);
}

/** @return string[] */
function buildBaseDockerComposeCmd(): array
{
    $appEnvironment = (string) variable('APP.ENVIRONMENT');
    $root = PathHelper::getRoot();

    $dockerEnvFile = $root . '/' . variable('DOCKER.ENV_FILE');
    $envCompose = 'compose.' . $appEnvironment . '.yaml';

    if (!fs()->exists($root . '/' . $envCompose)) {
        throw new ProblemException('Specific Docker Compose not found');
    }

    if (!fs()->exists($dockerEnvFile)) {
        throw new ProblemException('Docker Compose config not found');
    }

    // ! prod ! ensure the file always exist to use as secret
    $composerAuthFile = $root . '/.docker/.composer-auth.json';
    if (
        'prod' === $appEnvironment
        && !fs()->exists($composerAuthFile)
    ) {
        fs()->dumpFile($composerAuthFile, '{}');
    }

    // always use base file + env specific + custom override if exists
    $composes = [
        'compose.yaml', // base
        $envCompose, // env specific
    ];

    if (fs()->exists($root . '/compose.override.yaml')) {
        $composes[] = 'compose.override.yaml'; // custom override
    }

    // build the base command
    $cmd = [
        'docker',
        'compose',
    ];

    foreach ($composes as $compose) {
        $cmd[] = '-f';
        $cmd[] = $root . '/' . $compose;
    }

    // finally, add the env file
    $cmd[] = '--env-file=' . $dockerEnvFile;

    return $cmd;
}

/** @param string[] $command */
function run(string $service, array $command, ?Context $context = null): Process
{
    return castor_run([...buildBaseDockerComposeCmd(), 'run', '--rm', $service, ...$command], context: $context);
}

/** @param string[] $command */
function exec(string $service, array $command, ?Context $context = null): Process
{
    return castor_run([...buildBaseDockerComposeCmd(), 'exec', $service, ...$command], context: $context);
}
