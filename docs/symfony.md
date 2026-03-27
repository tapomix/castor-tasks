# Symfony Tasks

Symfony tasks for project installation and Console command execution in Docker containers.

**Namespace:** `tapomix-symfony`

## Overview

These tasks provide project bootstrapping and a convenient way to execute Symfony Console commands inside your Docker PHP container.

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
| `--full` / `--no-full` | `-f` | `false` | Install full webapp metapackage |
| `--symfony-version` | `-s` | `^8.0` | Symfony version constraint |

**How it works:**

1. Creates a new Symfony skeleton project via `composer create-project`
2. Moves the files into the code directory
3. Configures Composer automatically:
   - Disables Docker recipe integration (project manages its own Docker infrastructure)
   - Restricts to official recipes only (prevents interactive prompts for contrib recipes)
   - Allows the PHPStan extension-installer plugin
4. If `--full`: runs `composer require webapp`
5. Installs dev dependencies (PHPStan, Rector, PHP-CS-Fixer, Twig-CS-Fixer, Maker Bundle, etc.)

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
