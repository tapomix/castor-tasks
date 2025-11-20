# Symfony Tasks

Symfony Console command wrapper that executes in Docker containers.

**Namespace:** `tapomix-symfony`

## Overview

This task provides a convenient way to execute Symfony Console commands inside your Docker PHP container.

The Console command is executed using `docker-compose exec` in the PHP service, so **the container must be started** to execute commands.

**Availability:** This task is only available when `APP_FRAMEWORK=symfony` in `.castor/.env.castor`.

## Available Tasks

### `console` - Execute Console Command

Execute any Symfony Console command in the PHP Docker container.

**Aliases:** `tapomix-symfony:console`, `console`

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
|----------|---------|-------------|
| `APP.FRAMEWORK` | `vanilla` | Must be set to `symfony` to enable these tasks |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name in docker-compose |

---
