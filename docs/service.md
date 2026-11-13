# Service Tasks

Generic tasks for the Docker services (Traefik, diun, dozzle...), run in the service directory.

**Namespace:** `tapomix-service`

## Requirements

The tasks run a plain `docker compose`, without `-f`: the service `.env` must pick the right compose files through
`COMPOSE_FILE`, so that `docker compose` typed by hand and the tasks see the same config.

```dotenv
# .env
APP_ENV=prod
COMPOSE_FILE=compose.yaml:compose.${APP_ENV}.yaml
```

> [!WARNING]
> Unlike the `tapomix-docker` tasks, built for the sites, these tasks never add compose files on their own: a file
> missing from `COMPOSE_FILE` is simply ignored. `update` refuses to run when `COMPOSE_FILE` is not set at all (neither
> in the environment nor in the `.env`): `docker compose` would silently load `compose.yaml` alone.

No `.castor/.env.castor` is needed: the castor context reads the `.env` of the service, declared in its `castor.php`:

```php
// .castor/castor.php
define('TAPOMIX_APP_ENV_FILE', '.env');
```

## Available Tasks

### `update` - Update a Service

Pull the latest code and images, rebuild, then recreate the containers.

**Aliases:** `tapomix-service:update`, `service:update`

```bash
castor service:update
```

**Behavior:**

1. Stops when `COMPOSE_FILE` is not set, before touching anything
2. `git pull`
3. `docker compose pull --ignore-buildable`
4. `docker compose build --pull` (nothing to do for an image-only service)
5. `docker compose up --detach --wait --force-recreate`

The containers are **always recreated**: Compose does not watch the content of the mounted config files, so a plain
`up -d` silently keeps the old config. The service is cut for a few seconds (up to the healthcheck), an accepted cost.

The task stops at the first failing step and exits non-zero.

### `check` - Check Requirements

The generic `check` task is part of the preset: `docker` and `git` available, the `.env` present. Handy on a fresh
VPS, before the first `update`.

```bash
castor check
```
