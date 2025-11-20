# Laravel Tasks

Laravel Artisan command wrapper that executes in Docker containers.

**Namespace:** `tapomix-laravel`

## Overview

This task provides a convenient way to execute Laravel Artisan commands inside your Docker PHP container.

The Artisan command is executed using `docker-compose exec` in the PHP service, so **the container must be started** to execute commands.

**Availability:** This task is only available when `APP_FRAMEWORK=laravel` in `.castor/.env.castor`.

## Available Tasks

### `artisan` - Execute Artisan Command

Execute any Laravel Artisan command in the PHP Docker container.

**Aliases:** `tapomix-laravel:artisan`, `artisan`

**Usage:**

```bash
castor artisan <arguments...>

# Examples
castor artisan route:list
castor artisan migrate:fresh --seed
castor artisan make:model Post
castor artisan queue:work
castor artisan cache:clear
```

**How it works:**

The task passes all arguments directly to Artisan inside the container:

```bash
# castor artisan migrate
# becomes:
docker compose (...options...) exec php php artisan migrate
```

---

## Configuration

### Enable Laravel Tasks

Set the framework in `.castor/.env.castor`:

```bash
APP_FRAMEWORK=laravel
```

### PHP Service Name

By default, Artisan commands execute in the `php` service. Override this in `.castor/.env.castor`:

```bash
# Use custom PHP service name
APP_SERVICE_PHP=phpfpm
# or
APP_SERVICE_PHP=app
```

### Context Variables Used

| Variable | Default | Description |
|----------|---------|-------------|
| `APP.FRAMEWORK` | `vanilla` | Must be set to `laravel` to enable these tasks |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name in docker-compose |

---
