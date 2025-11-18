<?php

namespace Tapomix\Castor\Db\Postgres;

use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\io;
use function Castor\variable;
use function Tapomix\Castor\Db\buildDumpFileName;
use function Tapomix\Castor\Db\escapeSqlQuotes;
use function Tapomix\Castor\Db\validateSql;
use function Tapomix\Castor\Docker\exec as docker_exec;

define('TAPOMIX_NAMESPACE_POSTGRES', 'tapomix-postgres');

#[AsTask(namespace: TAPOMIX_NAMESPACE_POSTGRES, description: 'Connect into PosgreSQL database', aliases: ['pg:connect'])]
function connect(): void
{
    io()->title('Connecting into PosgreSQL database');

    docker_exec((string) variable('DOCKER.SERVICES.DB'), ['psql', '-U', (string) variable('APP.DB.USER'), (string) variable('APP.DB.NAME')]);
}

/** @example : castor pg:sql "select * from my_table" */
#[AsTask(namespace: TAPOMIX_NAMESPACE_POSTGRES, description: 'Execute SQL query (read-only)', aliases: ['pg:sql'])]
function sql(string $sql): void
{
    $trimmedSql = \trim($sql);

    validateSql($trimmedSql);

    $escapedSql = escapeSqlQuotes($trimmedSql);

    docker_exec((string) variable('DOCKER.SERVICES.DB'), ['psql', '-U', (string) variable('APP.DB.USER'), '-d', (string) variable('APP.DB.NAME'), '-c', $escapedSql]);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_POSTGRES, description: 'Backup database', aliases: ['pg:backup'])]
function backup(string $timing): void
{
    io()->title('Create DB backup');

    docker_exec((string) variable('DOCKER.SERVICES.DB'), ['pg_dump', '-O', '-U', (string) variable('APP.DB.USER'), '-f', buildDumpFileName($timing), (string) variable('APP.DB.NAME')]);
}

#[AsTask(namespace: TAPOMIX_NAMESPACE_POSTGRES, description: 'Restore database', aliases: ['pg:restore'])]
function restore(string $timing): void
{
    io()->title('Restore DB backup');

    $localDump = buildDumpFileName($timing, true);

    if (!fs()->exists($localDump)) {
        io()->error(\sprintf('Dump "%s" not found', $localDump));

        return;
    }

    $name = (string) variable('APP.DB.NAME');
    $user = (string) variable('APP.DB.USER');
    $service = (string) variable('DOCKER.SERVICES.DB');

    $containerDump = buildDumpFileName($timing);

    docker_exec($service, ['dropdb', '-U', $user, $name]);
    docker_exec($service, ['createdb', '-U', $user, $name]);
    docker_exec($service, ['psql', '-U', $user, '-f', $containerDump, $name]);

    io()->success('Dump ' . $timing . ' loaded');
}
