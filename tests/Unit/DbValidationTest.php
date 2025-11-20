<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Tapomix\Castor\Db\escapeSqlQuotes;
use function Tapomix\Castor\Db\validateNoMultiStatements;
use function Tapomix\Castor\Db\validateReadOnlyCommand;
use function Tapomix\Castor\Db\validateSql;
use function Tapomix\Castor\Db\validateSqlNotEmpty;

class DbValidationTest extends TestCase
{
    public function testValidateSqlNotEmptyThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query cannot be empty');

        validateSqlNotEmpty('');
    }

    public function testValidateSqlNotEmptyAcceptsNonEmptyString(): void
    {
        validateSqlNotEmpty('SELECT * FROM users');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateNoMultiStatementsThrowsOnSemicolon(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple statements are not allowed');

        validateNoMultiStatements('SELECT * FROM users; DROP TABLE users');
    }

    public function testValidateNoMultiStatementsAcceptsSingleStatement(): void
    {
        validateNoMultiStatements('SELECT * FROM users WHERE id = 1');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateReadOnlyCommandAcceptsSelect(): void
    {
        validateReadOnlyCommand('SELECT * FROM users');
        validateReadOnlyCommand('  SELECT id FROM posts');
        validateReadOnlyCommand('select name from products');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateReadOnlyCommandAcceptsShow(): void
    {
        validateReadOnlyCommand('SHOW TABLES');
        validateReadOnlyCommand('show databases');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateReadOnlyCommandAcceptsExplain(): void
    {
        validateReadOnlyCommand('EXPLAIN SELECT * FROM users');
        validateReadOnlyCommand('explain analyze select id from posts');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateReadOnlyCommandAcceptsWith(): void
    {
        validateReadOnlyCommand('WITH cte AS (SELECT id FROM users) SELECT * FROM cte');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateReadOnlyCommandRejectsInsert(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only read-only queries are allowed');

        validateReadOnlyCommand('INSERT INTO users (name) VALUES ("test")');
    }

    public function testValidateReadOnlyCommandRejectsUpdate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only read-only queries are allowed');

        validateReadOnlyCommand('UPDATE users SET name = "test"');
    }

    public function testValidateReadOnlyCommandRejectsDelete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only read-only queries are allowed');

        validateReadOnlyCommand('DELETE FROM users');
    }

    public function testValidateReadOnlyCommandRejectsDrop(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only read-only queries are allowed');

        validateReadOnlyCommand('DROP TABLE users');
    }

    public function testValidateSqlRunsAllValidations(): void
    {
        // Should pass all validations
        validateSql('SELECT * FROM users WHERE id = 1');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateSqlThrowsOnEmptyQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL query cannot be empty');

        validateSql('');
    }

    public function testValidateSqlThrowsOnMultipleStatements(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple statements are not allowed');

        validateSql('SELECT * FROM users; SELECT * FROM posts');
    }

    public function testValidateSqlThrowsOnWriteOperation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only read-only queries are allowed');

        validateSql('UPDATE users SET active = 1');
    }

    public function testEscapeSqlQuotesEscapesSingleQuotes(): void
    {
        $result = escapeSqlQuotes("It's a test");
        $this->assertSame("It''s a test", $result);
    }

    public function testEscapeSqlQuotesHandlesMultipleQuotes(): void
    {
        $result = escapeSqlQuotes("'quoted' and 'more quotes'");
        $this->assertSame("''quoted'' and ''more quotes''", $result);
    }

    public function testEscapeSqlQuotesHandlesNoQuotes(): void
    {
        $result = escapeSqlQuotes("No quotes here");
        $this->assertSame("No quotes here", $result);
    }
}
