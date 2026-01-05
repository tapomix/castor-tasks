# Tests

This directory contains the test suite for the Tapomix Castor Tasks collection.

## Structure

```tree
tests/
├── Unit/                          # Unit tests for helper functions
│   ├── Dns/
│   │   └── DnsToolsTest.php
│   ├── Enums/
│   │   ├── DNSSecAlgorithmTest.php
│   │   ├── DNSSecFlagTest.php
│   │   └── DNSZoneContextTest.php
│   ├── DbValidationTest.php
│   └── GeneratorTest.php
├── Integration/                   # Basic integration tests
│   └── CheckTaskTest.php
├── autoload.php                   # Auto-loads all PHP files from src/ for testing
├── TaskTestCase.php               # Base test class for task testing
└── README.md                      # This file
```

## Running Tests

### Using Castor (Recommended)

```bash
# Run all tests
castor test

# Run specific test suite
castor test --testsuite=Unit
castor test --testsuite=Integration

# Run with coverage report in terminal
castor test --coverage-text

# Run specific test file
castor test tests/Unit/DbValidationTest.php

# Run with filter
castor test --filter=testValidateSql
```

### Using Docker directly

If you need to run PHPUnit with specific options not wrapped by Castor:

```bash
# Run via docker compose
docker compose run --rm php-qa vendor/bin/phpunit

# Run specific test suite
docker compose run --rm php-qa vendor/bin/phpunit --testsuite=Unit

# Run specific test
docker compose run --rm php-qa vendor/bin/phpunit tests/Unit/DbValidationTest.php
```

## Writing Tests

### Unit Tests

Unit tests should test individual helper functions in isolation. They are fast and don't require external dependencies.

**Example:**

```php
<?php

namespace Tapomix\Castor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use function Tapomix\Castor\Db\validateSql;

class MyTest extends TestCase
{
    public function testValidateSqlAcceptsSelectQuery(): void
    {
        validateSql('SELECT * FROM users');

        $this->assertTrue(true); // No exception = success
    }

    public function testValidateSqlRejectsInsert(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        validateSql('INSERT INTO users (name) VALUES ("test")');
    }
}
```

## Test Categories

### Unit Tests (`tests/Unit/`)

- **DbValidationTest** - Tests for database SQL validation functions (19 tests)
  - SQL query validation (empty, multi-statements, read-only)
  - SQL quote escaping
- **GeneratorTest** - Tests for password and token generation (18 tests)
  - Password generation with various lengths
  - Password complexity requirements (lowercase, uppercase, numbers, special chars)
  - Token generation with hexadecimal output
  - Randomness verification
- **Dns/DnsToolsTest** - Tests for DNS zone management tools (61 tests)
  - Zone name validation
  - Zone file path building
  - DNSSEC key extraction (tag, data, algorithm)
  - Serial number computation and extraction
  - File loading utilities
- **Enums/DNSSecAlgorithmTest** - Tests for DNSSEC algorithm enum (20 tests)
- **Enums/DNSSecFlagTest** - Tests for DNSSEC flag enum (20 tests)
- **Enums/DNSZoneContextTest** - Tests for DNS zone context enum (20 tests)

### Integration Tests (`tests/Integration/`)

- **CheckTaskTest** - Basic tests for Castor binary availability (2 tests)

**Note**: Integration tests for Castor tasks are limited due to technical constraints when Castor is used as a library dependency. Unit tests provide excellent coverage of all helper functions and utilities. Task execution should be tested manually in real applications.

## Best Practices

1. **Keep unit tests fast** - They should run in milliseconds
2. **Test both success and failure paths** - Don't just test the happy path
3. **Use descriptive test names** - `testValidateSqlThrowsOnEmptyQuery` is better than `testValidate`
4. **Extract logic into helper functions** - Makes code testable (like `generatePassword()` and `generateToken()`)
5. **Test edge cases** - Minimum values, maximum values, empty inputs, etc.

## Quick Reference

```bash
# Run all tests
castor test

# Run only unit tests (fastest)
castor test --testsuite=Unit

# Run specific test file
castor test tests/Unit/DbValidationTest.php

# Run with filter
castor test --filter=testValidateSql
```

## Adding New Tests

When adding new helper functions to `src/`:

1. **Extract business logic** into a testable function (not inside a Castor task)
2. **Create a test file** in `tests/Unit/`
3. **Run the tests**: `castor test`

**That's it!** The `tests/autoload.php` file automatically loads all PHP files from `src/` recursively (except excluded files like `context.php` and `castor.dist.php`). No need to manually update `composer.json`.

### Autoload System

The test suite uses a custom autoloader (`tests/autoload.php`) that:

- Recursively scans all PHP files in `src/`
- Automatically loads them for testing (via `composer.json` `autoload-dev`)
- Skips excluded files (templates, already-loaded files)

To exclude a file from autoloading, add it to the `$excludedFiles` array in `tests/autoload.php`.
