# Database Tasks

PostgreSQL database operations that execute in Docker containers.

**Namespace:** `tapomix-postgres`

## Overview

These tasks provide convenient wrappers for PostgreSQL database operations inside your Docker database container.

All database commands are executed using `docker-compose exec` in the database service, so **the container must be started** to execute commands.

## Available Tasks

### `pg:connect` - Connect to Database

Open an interactive PostgreSQL shell connection to your database.

**Aliases:** `tapomix-postgres:connect`, `pg:connect`

**Usage:**

```bash
castor pg:connect
```

**How it works:**

```bash
# castor pg:connect
# becomes:
docker compose (...options...) exec db psql -U user db
```

Once connected, you can execute SQL commands interactively. Type `\q` to exit.

---

### `pg:sql` - Execute SQL Query

Execute a read-only SQL query directly from the command line.

**Aliases:** `tapomix-postgres:sql`, `pg:sql`

**Usage:**

```bash
castor pg:sql "SELECT * FROM users"
castor pg:sql "SELECT COUNT(*) FROM posts WHERE status = 'published'"
```

**How it works:**

```bash
# castor pg:sql "SELECT * FROM users"
# becomes:
docker compose (...options...) exec db psql -U user -d db -c "SELECT * FROM users"
```

**Security restrictions:**

The task validates queries to ensure safety:

- **Not empty** - Query cannot be empty
- **Single statement** - No semicolons allowed (prevents multiple statements)
- **Read-only** - Only `SELECT`, `SHOW`, `EXPLAIN`, `WITH`, `DESCRIBE`, `DESC` commands are allowed

**Example error:**

```bash
# This will fail (write operation)
castor pg:sql "DELETE FROM users"
# Error: Only read-only queries are allowed

# This will fail (multiple statements)
castor pg:sql "SELECT 1; SELECT 2"
# Error: Multiple statements are not allowed
```

---

### `pg:backup` - Backup Database

Create a database backup with a timestamp identifier.

**Aliases:** `tapomix-postgres:backup`, `pg:backup`

**Usage:**

```bash
# Backup with timestamp
castor pg:backup 2024-11-19-1430
castor pg:backup $(date +%Y%m%d-%H%M)
```

**Arguments:**

- `<timing>` - Timestamp or identifier for the backup (e.g., `2024-11-19-1430`)

**How it works:**

```bash
# castor pg:backup 2024-11-19-1430
# becomes:
docker compose (...options...) exec db pg_dump -O -U user -f /sql/dump-app-2024-11-19-1430.sql db
```

**Backup location:**

Backups are saved in `.docker/db/sql/` directory with the naming pattern:

```txt
dump-{APP_NAME}-{timing}.sql
```

**Example:**

```txt
.docker/db/sql/dump-myapp-2024-11-19-1430.sql
```

---

### `pg:restore` - Restore Database

Restore a database from a previous backup.

**Aliases:** `tapomix-postgres:restore`, `pg:restore`

**Usage:**

```bash
# Restore from backup
castor pg:restore 2024-11-19-1430
```

**Arguments:**

- `<timing>` - Timestamp or identifier of the backup to restore

**How it works:**

The restore process:

1. **Drop existing database**

   ```bash
   docker compose (...options...) exec db dropdb -U user db
   ```

2. **Create new database**

   ```bash
   docker compose (...options...) exec db createdb -U user db
   ```

3. **Restore from backup**

   ```bash
   docker compose (...options...) exec db psql -U user -f /sql/dump-app-2024-11-19-1430.sql db
   ```

**Important:** This operation will **completely replace** your current database with the backup data. All existing data will be lost.

**Validation:**

The task checks if the backup file exists before attempting to restore. If not found, it displays an error and stops.

---

## Configuration

### Database Service Name

By default, database commands execute in the `db` service. Override this in `.castor/.env.castor`:

```bash
# Use custom database service name
APP_SERVICE_DB=database
# or
APP_SERVICE_DB=postgres
```

### Database Credentials

Configure database connection details:

```bash
# Database name
APP_DB_NAME=myapp_db

# Database user
APP_DB_USER=myapp_user
```

### Application Name

The application name is used in backup file naming:

```bash
APP_NAME=myapp
```

### Backup Directory Structure

Ensure the backup directory exists in your project:

```tree
project-root/
└── .docker/
    └── db/
        └── sql/  # Backup files stored here
```

This directory should be mounted as a volume in your `compose.yaml`:

```yaml
services:
  db:
    image: postgres:16-alpine
    volumes:
      - .docker/db/sql:/sql
```

### Context Variables Used

| Variable | Default | Description |
|----------|---------|-------------|
| `APP.NAME` | `app` | Application name (used in backup file naming) |
| `APP.DB.NAME` | `db` | Database name |
| `APP.DB.USER` | `user` | Database user |
| `DOCKER.SERVICES.DB` | `db` | Database service name in docker-compose |

---
