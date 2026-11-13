# Docker Tasks

Docker Compose orchestration and container management tasks.

**Namespace:** `tapomix-docker`

## Prerequisites

The tasks run a plain `docker compose`, without `-f`, `--profile` or `--env-file`: the project `.env` declares the
compose files (`COMPOSE_FILE`) and the profiles (`COMPOSE_PROFILES`). A hand-typed `docker compose` at the project
root therefore sees the same config as the tasks. Shared by the sites (`php` preset) and the Docker services
(`service` preset).

**A site** loads four fixed levels, the same line on every machine:

```dotenv
# .env
APP_ENV=dev # dev | prod
COMPOSE_PROFILES=${APP_ENV}
COMPOSE_FILE=compose.yaml:compose.${APP_ENV}.yaml:compose.app.yaml:compose.override.yaml
```

```tree
project-root/
├── compose.yaml           # base
├── compose.dev.yaml       # dev
├── compose.prod.yaml      # prod
├── compose.app.yaml       # project-wide layer (committed)
├── compose.override.yaml  # machine-local layer (gitignored), required: comments only when empty
└── .env                   # app env file, fixed name
```

**A service** lists its own files, e.g. `COMPOSE_FILE=compose.yaml:compose.override.yaml`.

### Validation

Compose loads what `COMPOSE_FILE` lists and silently ignores the rest. Before any compose command, after the
`${APP_ENV}` interpolation, the tasks (and `castor check`) stop with the expected value when:

- `COMPOSE_FILE` is not set, in any project;
- for a site (the `php` preset defines `TAPOMIX_PROJECT_SITE`): `COMPOSE_FILE` is not exactly the four levels above,
  or `COMPOSE_PROFILES` differs from `APP_ENV`.

| Mistake | Caught by |
| --- | --- |
| A listed file absent from disk (`compose.override.yaml`) | Compose |
| A file missing from a site `COMPOSE_FILE`, wrong order, wrong env file | The tasks |
| `COMPOSE_FILE` / `COMPOSE_PROFILES` not set | The tasks |

A value exported in the shell wins over `.env`, for Compose as for the validation.

The tasks run Compose with the project root as working directory: they work from a subdirectory (`code/`), where a
hand-typed `docker compose` finds `compose.yaml` but skips `.env`.

### Layer order

```txt
compose.yaml > compose.{env}.yaml > compose.app.yaml > compose.override.yaml
```

`compose.app.yaml` is the slot for configuration specific to a project but common to every
environment and every developer — it is **committed**, unlike `compose.override.yaml`. It sits
*after* the environment file so it can override an infra default, which matters when the base
files are synced from a template and must not be edited locally.

### Environment-specific services

A service that only makes sense in one environment lives in `compose.app.yaml` too, gated by a
Compose profile:

```yaml
services:
  queue:
    profiles: [prod]
    # ...
```

`COMPOSE_PROFILES=${APP_ENV}` activates them for every command, so profiled services follow the
whole lifecycle — a `stop` tears down what a `start` created. Services with no `profiles` key are
always active.

## Available Tasks

### `build` - Build All Services

Build all Docker services defined in your compose files.

**Aliases:** `tapomix-docker:build`, `build`

**Usage:**

```bash
# Build all services
castor build

# Build without using cache
castor build --no-cache

# Build from the locally cached base images (e.g. offline)
castor build --no-pull
```

**Options:**

- `--no-cache` - Build images without using cache
- `--no-pull` - Skip pulling the base images (pulled by default, so a republished tag is picked up)

---

### `pull` - Pull Fresh Images

Pull the latest versions of all images defined in your compose files.

**Aliases:** `tapomix-docker:pull`, `pull`

**Usage:**

```bash
castor pull
```

**Note:** This command uses `--ignore-buildable` to only pull images that are not built locally.

---

### `start` - Start All Services

Start all services in detached mode and wait for them to be healthy.

**Aliases:** `tapomix-docker:start`, `start`, `up`

**Usage:**

```bash
castor start
# or
castor up

# Update the images first: pull the non-buildable ones, then build (base images pulled)
castor start --update
castor start -u
```

**Options:**

- `--update` / `-u` - Pull and build the images before starting (equivalent to `castor pull` then `castor build`)

**Behavior:**

- Services start in detached mode (`--detach`)
- Command waits for services to be healthy (`--wait`)
- Only the containers whose image changed are recreated, the others keep running

`--update` applies an environment change without a full deploy, e.g. a new `PHP_VERSION` in the `.env` file
or an image tag republished with a security fix. It needs network access: offline, run `castor start` alone.

---

### `stop` - Stop All Services

Stop all running services and remove orphan containers.

**Aliases:** `tapomix-docker:stop`, `stop`, `down`

**Usage:**

```bash
castor stop
# or
castor down
```

**Behavior:**

- Stops all services
- Removes orphan containers (`--remove-orphans`)

---

### `logs` - Show Server Logs

Display and follow logs from all services.

**Aliases:** `tapomix-docker:logs`, `logs`

**Usage:**

```bash
castor logs
```

**Behavior:**

- Follows logs in real-time (`-f`)
- Press `Ctrl+C` to exit

---

### `shell` - Open Terminal in Container

Open an interactive shell in a running container.

**Aliases:** `tapomix-docker:shell`, `shell`

**Usage:**

```bash
castor shell <service> [--shell=<shell>]

# Examples
castor shell php               # Open bash shell in PHP service (default)
castor shell db --shell=sh     # Open sh shell in database service
castor shell node --shell=zsh  # Open zsh shell in Node service
```

**Arguments:**

- `<service>` - Name of the service to connect to

**Options:**

- `--shell=<shell>` - Shell to use (default: `bash`). Can be `bash`, `sh`, `zsh`, etc.

---

## Helper Functions

The following helper functions are available for use in your custom tasks:

### `buildBaseDockerComposeCmd()`

Validates `COMPOSE_FILE` (see [Validation](#validation)) and returns the base command.

**Returns:** `string[]` - `['docker', 'compose']`

Run it through `composeContext()`, which sets the project root as working directory: Compose reads `.env` and
resolves `COMPOSE_FILE` from there.

**Example usage in custom task:**

```php
use function Castor\run;
use function Tapomix\Castor\Docker\buildBaseDockerComposeCmd;
use function Tapomix\Castor\Docker\composeContext;

#[AsTask()]
function my_docker_task(): void
{
    $cmd = [...buildBaseDockerComposeCmd(), 'cmd', '--args'];
    run($cmd, context: composeContext());
}
```

---

### `run()`

Execute a command in a service using `docker-compose run`.

**Signature:**

```php
function run(string $service, array $command, ?Context $context = null): Process
```

**Parameters:**

- `$service` - Service name
- `$command` - Command to execute (array of arguments)
- `$context` - Optional Castor context

**Returns:** `Process` - Symfony Process instance

A failing command stops the task with a Castor error, unless the context allows failures
(`context()->withAllowFailure()`): the returned `Process` can then be checked with `isSuccessful()`.

**Example:**

```php
use function Tapomix\Castor\Docker\run as docker_run;

#[AsTask()]
function my_task(): void
{
    // Run a one-off command in the PHP service
    docker_run('php', ['php', '-v']);
}
```

**Docker command generated:**

```bash
docker compose run --rm php php -v   # from the project root
```

---

### `exec()`

Execute a command in a running service using `docker-compose exec`.

**Signature:**

```php
function exec(string $service, array $command, ?Context $context = null, array $env = []): Process
```

**Parameters:**

- `$service` - Service name
- `$command` - Command to execute (array of arguments)
- `$context` - Optional Castor context
- `$env` - Optional `['KEY' => 'value']` map, passed as `-e KEY=value` to `docker compose exec` (scoped to this call)

**Returns:** `Process` - Symfony Process instance

A failing command stops the task with a Castor error, unless the context allows failures
(`context()->withAllowFailure()`): the returned `Process` can then be checked with `isSuccessful()`.

**Example:**

```php
use function Tapomix\Castor\Docker\exec as docker_exec;

#[AsTask()]
function my_task(): void
{
    // Execute command in running PHP service
    docker_exec('php', ['composer', 'install']);

    // With an extra environment variable, scoped to this call
    docker_exec('php', ['vendor/bin/phpunit', '--coverage-text'], env: ['XDEBUG_MODE' => 'coverage']);
}
```

**Docker command generated:**

```bash
docker compose exec php composer install   # from the project root

# with env: ['XDEBUG_MODE' => 'coverage']
docker compose exec -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text
```

---

## Configuration

### Required Environment Variables

Set in the project `.env`: `APP_ENV`, `COMPOSE_FILE` and, for a site, `COMPOSE_PROFILES` (see
[Prerequisites](#prerequisites)).

### Optional Context Variables

Configure in `.castor/.env.castor`:

```bash
# Override default Docker service names
APP_SERVICE_DB=database
APP_SERVICE_NODE=nodejs
APP_SERVICE_PHP=phpfpm
```

### Context Variables Used

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `APP.ENVIRONMENT` | `APP_ENV`, then `dev` | Application environment (expected env level of a site) |
| `COMPOSE.FILE` | `COMPOSE_FILE`, then empty | Validated compose files |
| `COMPOSE.PROFILES` | `COMPOSE_PROFILES`, then empty | Validated profiles of a site |
| `DOCKER.SERVICES.DB` | `db` | Database service name |
| `DOCKER.SERVICES.NODE` | `node` | Node service name |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name |

---
