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
> missing from `COMPOSE_FILE` is simply ignored.

## Available Tasks

### `update` - Update a Service

Pull the latest code and images, rebuild, then recreate the containers.

**Aliases:** `tapomix-service:update`, `service:update`

```bash
castor service:update
```

**Behavior:**

1. `git pull`
2. `docker compose pull --ignore-buildable`
3. `docker compose build --pull` (nothing to do for an image-only service)
4. `docker compose up --detach --wait --force-recreate`

The containers are **always recreated**: Compose does not watch the content of the mounted config files, so a plain
`up -d` silently keeps the old config. The service is cut for a few seconds (up to the healthcheck), an accepted cost.

The task stops at the first failing step and exits non-zero.
