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

The validation is shared with the docker tasks (see [docs/docker.md](docker.md#validation)): a service lists its own
files, only an unset `COMPOSE_FILE` is refused. The castor context reads the `.env` of the service; no
`.castor/.env.castor` is needed.

## Available Tasks

The `service` preset also brings the [docker tasks](docker.md), the same commands as the sites:

```bash
castor start   # up -d, waits for the healthchecks
castor stop    # down --remove-orphans
castor logs    # follow, with timestamps
castor build   # pulls the base images
castor pull    # non-buildable images
castor shell <service>
```

### `update` - Update a Service

Pull the latest code and images, rebuild, then recreate the containers.

**Aliases:** `tapomix-service:update`, `service:update`

```bash
castor service:update
```

**Behavior:**

1. Stops when `COMPOSE_FILE` is not set, before touching anything
2. `git pull`
3. `castor castor:composer install`, only when the pull brought a new `.castor/castor.composer.lock`
4. `docker compose pull --ignore-buildable`
5. `docker compose build --pull` (nothing to do for an image-only service)
6. `docker compose up --detach --wait --force-recreate`

The containers are **always recreated**: Compose does not watch the content of the mounted config files, so a plain
`up -d` silently keeps the old config. The service is cut for a few seconds (up to the healthcheck), an accepted cost.

Castor does not reinstall its own dependencies when only the lock changes (a new castor-tasks version under the same
constraint), hence step 3. The run in progress already loaded the previous tasks: the new ones apply from the next run.
A failing install (network, GitHub) does not block the service: the task warns, with the command to run by hand, and
goes on.

The task stops at the first failing step and exits non-zero.

### `check` - Check Requirements

The generic `check` task is part of the preset: `docker` and `git` available, the `.env` present. Handy on a fresh
VPS, before the first `update`.

```bash
castor check
```
