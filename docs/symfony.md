# Symfony Tasks

Symfony tasks for project installation, Console command execution and running the test suite in Docker containers.

**Namespace:** `tapomix-symfony`

## Overview

These tasks provide project bootstrapping, a convenient way to execute Symfony Console commands, and a PHPUnit runner — all inside your Docker PHP container.

The Console command is executed using `docker-compose exec` in the PHP service, so **the container must be started** to execute commands.

**Availability:** These tasks are only available when `APP_FRAMEWORK=symfony` in `.castor/.env.castor`.

## Available Tasks

### `install` - Install New Symfony Project

Bootstrap a new Symfony project inside the PHP Docker container.

**Aliases:** `tapomix-symfony:install`, `symfony`, `symfony:install`

**Usage:**

```bash
castor symfony:install [--full] [--symfony-version=<version>]

# Minimal install (skeleton only, ideal for API)
castor symfony:install

# Full webapp (adds Twig, Doctrine, Security, Profiler)
castor symfony:install --full

# Specific Symfony version
castor symfony:install --symfony-version='^7.2'
castor symfony:install --full --symfony-version='^7.2'
```

**Options:**

| Option | Shortcut | Default | Description |
| ------ | -------- | ------- | ----------- |
| `--full` | `-f` | `false` | Install full webapp metapackage |
| `--symfony-version` | `-s` | `^8.0` | Symfony version constraint |

**How it works:**

1. Creates a new Symfony skeleton project via `composer create-project`
2. Moves the files into the code directory
3. Configures Composer automatically:
   - Disables Docker recipe integration (project manages its own Docker infrastructure)
   - Restricts to official recipes only (prevents interactive prompts for contrib recipes)
   - Allows the PHPStan extension-installer plugin
   - Aligns `require.php` with the PHP running in the container (`>=8.5` on PHP 8.5.x), instead of the skeleton's Symfony minimum
4. If `--full`: runs `composer require webapp`
5. Installs dev dependencies (PHPStan, Rector, PHP-CS-Fixer, Twig-CS-Fixer, Maker Bundle, etc.)
6. Removes the recipe files superseded by the project-level configuration: `.editorconfig` (its `root = true` would shadow the project one), `AGENTS.md` and `CLAUDE.md`

---

### `console` - Execute Console Command

Execute any Symfony Console command in the PHP Docker container.

**Aliases:** `tapomix-symfony:console`, `console`, `symfony:console`

**Usage:**

```bash
castor console <arguments...>

# Examples
castor console cache:clear
castor console debug:router
castor console make:controller HomeController
castor console doctrine:migrations:migrate
castor console messenger:consume async
```

**How it works:**

The task passes all arguments directly to Console inside the container:

```bash
# castor console cache:clear
# becomes:
docker compose (...options...) exec php php bin/console cache:clear
```

---

### `test` - Run PHPUnit Tests

Run the PHPUnit test suite in the PHP Docker container.

**Aliases:** `tapomix-symfony:test`, `test`

**Availability:** Symfony projects only (`APP_FRAMEWORK=symfony`), dev environment only

**Usage:**

```bash
# Run the whole suite
castor test

# Only run tests matching a pattern
castor test --filter=SomeTest
castor test -f testSomething

# With a code coverage report
castor test --coverage
```

**Options:**

| Option | Shortcut | Default | Description |
| ------ | -------- | ------- | ----------- |
| `--filter` | `-f` | — | Only run tests whose name matches this pattern |
| `--coverage` | — | `false` | Generate a coverage report (appends `--coverage-text`) |

**How it works:**

```bash
# castor test
# becomes:
docker compose (...options...) exec php vendor/bin/phpunit

# castor test --coverage
# becomes:
docker compose (...options...) exec -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text
```

`--coverage` sets `XDEBUG_MODE=coverage` for that run only, so it works even when the project
keeps Xdebug in a lighter mode (`develop,debug`) for day-to-day speed. A coverage driver
(Xdebug or PCOV) must be present in the container.

The task exits with PHPUnit's exit code, so a failing or crashing suite can be caught by a CI,
a git hook or `castor test && ...`.

A failing suite makes the task exit non-zero without raising a Castor error.

**Requirements:**

- `phpunit/phpunit` must be installed

---

## Configuration

### Enable Symfony Tasks

Set the framework in `.castor/.env.castor`:

```bash
APP_FRAMEWORK=symfony
```

### PHP Service Name

By default, Console commands execute in the `php` service. Override this in `.castor/.env.castor`:

```bash
# Use custom PHP service name
APP_SERVICE_PHP=phpfpm
# or
APP_SERVICE_PHP=app
```

### Context Variables Used

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `APP.FRAMEWORK` | `vanilla` | Must be set to `symfony` to enable these tasks |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name in docker-compose |

---
