<?php

namespace Tapomix\Castor\Db;

use function Castor\variable;

/**
 * Build database dump file name with proper path
 *
 * @param string $timing Timestamp or date identifier for the dump (e.g., '202401151430')
 * @param bool   $local  Whether to generate a local path (true) or container path (false)
 */
function buildDumpFileName(string $timing, bool $local = false): string
{
    $dumpName = '/dump-' . variable('APP.NAME') . '-' . $timing . '.sql';

    return $local
        ? '.docker/db/sql' . $dumpName
        : '/sql' . $dumpName
    ;
}

/**
 * Run all validations on SQL query (composite validator)
 *
 * @throws \InvalidArgumentException
 */
function validateSql(string $sql): void
{
    validateSqlNotEmpty($sql);
    validateNoMultiStatements($sql);
    validateReadOnlyCommand($sql);
}

/**
 * Validate that SQL query is not empty
 *
 * @throws \InvalidArgumentException
 */
function validateSqlNotEmpty(string $sql): void
{
    if ('' === $sql) {
        throw new \InvalidArgumentException('SQL query cannot be empty');
    }
}

/**
 * Validate that SQL query does not contain multiple statements ( = no semicolon )
 *
 * @throws \InvalidArgumentException
 */
function validateNoMultiStatements(string $sql): void
{
    if (\str_contains($sql, ';')) {
        throw new \InvalidArgumentException('Multiple statements are not allowed');
    }
}

/**
 * Validate that SQL query is read-only
 *
 * @throws \InvalidArgumentException
 */
function validateReadOnlyCommand(string $sql): void
{
    // ? DESCRIBE/DESC exists in MySQL but not in PostgreSQL (will fail at execution)
    $allowedCommands = ['SELECT', 'SHOW', 'EXPLAIN', 'WITH', 'DESCRIBE', 'DESC'];

    foreach ($allowedCommands as $command) {
        if (\preg_match('/^\s*' . \preg_quote($command, '/') . '\b/i', $sql)) {
            return;
        }
    }

    throw new \InvalidArgumentException('Only read-only queries are allowed');
}

/** Escape single quotes for SQL (works for PostgreSQL, MySQL) */
function escapeSqlQuotes(string $sql): string
{
    return \str_replace("'", "''", $sql);
}
