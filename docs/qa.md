# QA Tasks

Quality assurance and code analysis tools that execute in Docker containers.

**Namespace:** `tapomix-qa`

## Overview

These tasks provide convenient wrappers for running PHP quality assurance tools inside your Docker PHP container.

All QA commands are executed using `docker-compose exec` in the PHP service, so **the container must be started** to execute commands.

**Important:** Tasks automatically skip with a warning if the required binary is not found in your project.

## Available Tasks

### `qa` - Run All QA Analyzers

Execute all available QA analyzers sequentially or in parallel.

**Aliases:** `tapomix-qa:all`, `qa`

**Usage:**

```bash
castor qa

# Run analyzers in parallel
castor qa --parallel
```

**Options:**

- `--parallel` - Execute all analyzers concurrently

**How it works:**

The task automatically detects all available QA tools in your project and runs them one by one (or in parallel if `--parallel` is specified).  
Only tools with binaries present are executed.

---

### `lint` - Lint Twig Templates

Lint Twig templates using Symfony's built-in linter.

**Aliases:** `tapomix-qa:lint`, `lint`

**Availability:** Symfony projects only (`APP_FRAMEWORK=symfony`)

**Usage:**

```bash
castor lint
```

**How it works:**

```bash
# castor lint
# becomes:
docker compose (...options...) exec php php bin/console lint:twig --show-deprecations templates/
```

**Requirements:**

- `symfony/twig-bundle` must be installed

---

### `php-cs` - Run PHP-CS-Fixer

Run PHP-CS-Fixer for code style analysis and fixes.

**Aliases:** `tapomix-qa:php-cs-fixer`, `php-cs`, `cs`

**Usage:**

```bash
# Dry-run (check only)
castor php-cs

# Apply fixes
castor php-cs --fix
```

**Options:**

- `--fix` / `-f` - Apply fixes (default: dry-run with diff)

**How it works:**

```bash
# castor php-cs
# becomes:
docker compose (...options...) exec php vendor/bin/php-cs-fixer fix --dry-run -vv --diff --show-progress=dots

# castor php-cs --fix
# becomes:
docker compose (...options...) exec php vendor/bin/php-cs-fixer fix
```

**Requirements:**

- `friendsofphp/php-cs-fixer` must be installed

---

### `phpstan` - Run PHPStan

Run PHPStan static analysis.

**Aliases:** `tapomix-qa:phpstan`, `phpstan`

**Usage:**

```bash
castor phpstan
```

**How it works:**

```bash
# castor phpstan
# becomes:
docker compose (...options...) exec php vendor/bin/phpstan analyse --memory-limit 256M
```

**Requirements:**

- `phpstan/phpstan` must be installed

---

### `pint` - Run Laravel Pint

Run Laravel Pint for code style analysis and fixes.

**Aliases:** `tapomix-qa:pint`, `pint`

**Availability:** Laravel projects only (`APP_FRAMEWORK=laravel`)

**Usage:**

```bash
# Dry-run (check only)
castor pint

# Apply fixes
castor pint --fix
```

**Options:**

- `--fix` / `-f` - Apply fixes (default: dry-run test mode)

**How it works:**

```bash
# castor pint
# becomes:
docker compose (...options...) exec php vendor/bin/pint app/ --test -v

# castor pint --fix
# becomes:
docker compose (...options...) exec php vendor/bin/pint app/
```

**Requirements:**

- `laravel/pint` must be installed

---

### `rector` - Run Rector

Run Rector for automated code refactoring and upgrades.

**Aliases:** `tapomix-qa:rector`, `rector`

**Usage:**

```bash
# Dry-run (check only)
castor rector

# Apply fixes
castor rector --fix
```

**Options:**

- `--fix` / `-f` - Apply fixes (default: dry-run with debug)

**How it works:**

```bash
# castor rector
# becomes:
docker compose (...options...) exec php vendor/bin/rector process --dry-run --debug

# castor rector --fix
# becomes:
docker compose (...options...) exec php vendor/bin/rector process
```

**Requirements:**

- `rector/rector` must be installed

---

### `twig-cs` - Run Twig-CS-Fixer

Run Twig-CS-Fixer for Twig template code style analysis and fixes.

**Aliases:** `tapomix-qa:twig-cs-fixer`, `twig-cs`

**Availability:** Symfony projects only (`APP_FRAMEWORK=symfony`)

**Usage:**

```bash
# Dry-run (check only)
castor twig-cs

# Apply fixes
castor twig-cs --fix
```

**Options:**

- `--fix` / `-f` - Apply fixes (default: lint only)

**How it works:**

```bash
# castor twig-cs
# becomes:
docker compose (...options...) exec php vendor/bin/twig-cs-fixer lint --debug

# castor twig-cs --fix
# becomes:
docker compose (...options...) exec php vendor/bin/twig-cs-fixer lint --debug --fix
```

**Requirements:**

- `vincentlanglet/twig-cs-fixer` must be installed

---

## Configuration

### PHP Service Name

By default, QA commands execute in the `php` service. Override this in `.castor/.env.castor`:

```bash
# Use custom PHP service name
APP_SERVICE_PHP=phpfpm
```

### Code Path

If your code is in a subdirectory inside the container, configure the path:

```bash
# Code is in /app/code/ inside container
APP_CODE_PATH=code
```

If your code is at the root level of your project, use `.` as value.

### Context Variables Used

| Variable | Default | Description |
|----------|---------|-------------|
| `APP.CODE_PATH` | `code` | Path to application code in container |
| `APP.FRAMEWORK` | `vanilla` | Framework type (affects availability of `lint`, `pint`, `twig-cs`) |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name in docker-compose |

---
