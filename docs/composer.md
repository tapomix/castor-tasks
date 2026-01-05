# Composer Tasks

Composer command wrappers that execute in Docker containers.

**Namespace:** `tapomix-composer`

## Overview

These tasks provide a convenient way to execute Composer commands inside your Docker PHP container, ensuring consistency between development environments and avoiding local PHP version conflicts.

All Composer commands are executed using the `docker-compose exec` command in the PHP service, so **the container must be started** to execute commands.

## Available Tasks

### `composer` - Execute Composer Command

Execute any Composer command in the PHP Docker container.

**Aliases:** `tapomix-composer:exec`, `composer`

**Usage:**

```bash
castor composer <arguments...>

# Examples
castor composer install
castor composer update --dry-run
castor composer require symfony/http-client
castor composer remove vendor/package
castor composer dump-autoload
```

**How it works:**

The task passes all arguments directly to Composer inside the container:

```bash
# castor composer install
# becomes:
docker compose (...options...) exec php composer install
```

---

### `composer:dev` - Execute Composer Command with --dev

Execute Composer commands with the `--dev` flag (for development dependencies).

**Aliases:** `tapomix-composer:exec-dev`, `composer:dev`

**Usage:**

```bash
castor composer:dev <arguments...>

# Examples
castor composer:dev require phpunit/phpunit
castor composer:dev update
```

**How it works:**

The task appends `--dev` to all Composer arguments:

```bash
# castor composer:dev require phpunit/phpunit
# becomes:
docker compose (...options...) exec php composer require phpunit/phpunit --dev
```

---

### `composer:global` - Execute Global Composer Command

Execute Composer global commands in the PHP Docker container.

**Aliases:** `tapomix-composer:exec-global`, `composer:global`

**Usage:**

```bash
castor composer:global <arguments...>

# Examples
castor composer:global require friendsofphp/php-cs-fixer
castor composer:global update
castor composer:global remove package/name
castor composer:global show
```

**How it works:**

The task prepends `global` to all Composer arguments:

```bash
# castor composer:global require friendsofphp/php-cs-fixer
# becomes:
docker compose (...options...) exec php composer global require friendsofphp/php-cs-fixer
```

---

## Configuration

### PHP Service Name

By default, Composer commands execute in the `php` service. Override this in `.castor/.env.castor`:

```bash
# Use custom PHP service name
APP_SERVICE_PHP=phpfpm
# or
APP_SERVICE_PHP=app
```

### Context Variables Used

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name in docker-compose |

---
