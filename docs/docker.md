# Docker Tasks

Docker Compose orchestration and container management tasks.

**Namespace:** `tapomix-docker`

## Prerequisites

Your project must follow this Docker Compose file structure:

```tree
project-root/
├── compose.yaml           # Base compose file (required)
├── compose.dev.yaml       # Development compose file (required in dev)
├── compose.prod.yaml      # Production compose file (required in prod)
├── compose.override.yaml  # Optional local overrides (gitignored)
└── .env(.docker)          # Docker environment variables (required)
```

The tasks automatically:

- Load the base `compose.yaml` file
- Load environment-specific compose file based on `APP_ENVIRONMENT` variable
- Load `compose.override.yaml` if it exists
- Use the environment file defined in `DOCKER.ENV_FILE` (default: `.env.docker`)

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
```

**Options:**

- `--no-cache` - Build images without using cache

---

### `pull` - Pull Fresh Images

Pull the latest versions of all images defined in your compose files.

**Aliases:** `tapomix-docker:pull`

**Usage:**

```bash
castor tapomix-docker:pull
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
```

**Behavior:**

- Services start in detached mode (`--detach`)
- Command waits for services to be healthy (`--wait`)

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

Open an interactive bash shell in a running container.

**Aliases:** `tapomix-docker:shell`, `sh`

**Usage:**

```bash
castor sh <service>

# Examples
castor sh php   # Open shell in PHP service
castor sh db    # Open shell in database service
castor sh node  # Open shell in Node service
```

**Arguments:**

- `<service>` - Name of the service to connect to

---

## Helper Functions

The following helper functions are available for use in your custom tasks:

### `buildBaseDockerComposeCmd()`

Builds the base docker-compose command with all configuration files and environment variables.

**Returns:** `string[]` - Array of command parts

**Behavior:**

1. Checks for required files (environment-specific compose file and env file)
2. Creates `.docker/.composer-auth.json` if in production mode and file doesn't exist
3. Builds command with:
   - `compose.yaml` (base)
   - `compose.{environment}.yaml` (environment-specific)
   - `compose.override.yaml` (if exists)
   - `--env-file={DOCKER.ENV_FILE}`

**Example usage in custom task:**

```php
use function Tapomix\Castor\Docker\buildBaseDockerComposeCmd;
use function Castor\run;

#[AsTask()]
function my_docker_task(): void
{
    $cmd = array_merge(buildBaseDockerComposeCmd(), ['ps']);
    run($cmd);
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
docker compose -f compose.yaml -f compose.dev.yaml --env-file=.env.docker \
    run --rm php php -v
```

---

### `exec()`

Execute a command in a running service using `docker-compose exec`.

**Signature:**

```php
function exec(string $service, array $command, ?Context $context = null): Process
```

**Parameters:**

- `$service` - Service name
- `$command` - Command to execute (array of arguments)
- `$context` - Optional Castor context

**Returns:** `Process` - Symfony Process instance

**Example:**

```php
use function Tapomix\Castor\Docker\exec as docker_exec;

#[AsTask()]
function my_task(): void
{
    // Execute command in running PHP service
    docker_exec('php', ['composer', 'install']);
}
```

**Docker command generated:**

```bash
docker compose -f compose.yaml -f compose.dev.yaml --env-file=.env.docker \
    exec php composer install
```

---

## Configuration

### Required Environment Variables

Set in your app's environment file (default: `.env.docker`):

```bash
APP_ENVIRONMENT=dev  # or prod, staging, etc.
```

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
|----------|---------|-------------|
| `DOCKER.ENV_FILE` | `.env.docker` | Path to Docker environment file |
| `APP.ENVIRONMENT` | `dev` | Application environment (determines which compose file to load) |
| `DOCKER.SERVICES.DB` | `db` | Database service name |
| `DOCKER.SERVICES.NODE` | `node` | Node service name |
| `DOCKER.SERVICES.PHP` | `php` | PHP service name |

---
