# Node/NPM Tasks

NPM command wrapper that executes in Docker containers.

**Namespace:** `tapomix-node`

## Overview

This task provides a convenient way to execute NPM commands inside your Docker Node container.

The NPM command is executed using `docker-compose exec` in the Node service, so **the container must be started** to execute commands.

## Available Tasks

### `npm` - Execute NPM Command

Execute any NPM command in the Node Docker container.

**Aliases:** `tapomix-node:npm`, `npm`

**Usage:**

```bash
castor npm <arguments...>

# Examples
castor npm install
castor npm run build
castor npm install --save-dev tailwindcss
```

**How it works:**

The task passes all arguments directly to NPM inside the container:

```bash
# castor npm install
# becomes:
docker compose (...options...) exec node npm install
```

---

## Configuration

### Node Service Name

By default, NPM commands execute in the `node` service. Override this in `.castor/.env.castor`:

```bash
# Use custom Node service name
APP_SERVICE_NODE=nodejs
# or
APP_SERVICE_NODE=frontend
```

### Context Variables Used

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `DOCKER.SERVICES.NODE` | `node` | Node service name in docker-compose |

---
