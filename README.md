# Tapomix / Castor Tasks

Collection of reusable [Castor](https://castor.jolicode.com/) tasks for PHP development, Docker orchestration, database operations, quality assurance workflows, and development tools.

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Available Tasks](#available-tasks)
- [Development](#development)

## Installation

**Create** a file `.castor/castor.composer.json` in your project:

**For GitHub repository:**

```json
// .castor/castor.composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/tapomix/castor-tasks"
        }
    ],
    "require": {
        "tapomix/castor-tasks": "^0.5"
    }
}
```

**For Satis repository:**

```json
// .castor/castor.composer.json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://satis.DOMAIN.TLD"
        }
    ],
    "require": {
        "tapomix/castor-tasks": "^0.5"
    }
}
```

Then run:

```bash
castor castor:composer update
```

## Usage

**Copy** the package file to your project's `.castor/` directory:

```bash
cp .castor/vendor/tapomix/castor-tasks/dist/castor.dist.php .castor/castor.php
```

Or you can add this to your existing `castor.php` file:

```php
// .castor/castor.php
<?php

// Guard against redefinition when this file is imported/mounted by another castor.php
defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Helper\PathHelper;

use function Castor\import;

import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/php.php'); // import the tasks for a PHP project
```

**Why not remote-import?** Remote imports change the `workingDirectory` in the context, which we want to avoid.

### Presets

The tasks target unrelated projects (PHP apps, the DNS server, the Docker services): each project loads only the
groups it needs through the `presets/` directory. To use a preset, replace the `import` line in your `castor.php`
(**replace** `<preset>` with one from the list below):

```php
// .castor/castor.php

// ...
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/<preset>.php');
```

**Available presets:**

| Preset | Description |
| ------ | ----------- |
| `dns.php` | DNS/DNSSEC tasks only |
| `infra.php` | Infra sync tasks only |
| `php.php` | PHP site tasks, without `npm`: import `src/node` for it |
| `service.php` | Docker service tasks (`update`, the docker tasks), with `check` |
| `traefik.php` | Traefik site tasks, imported with `service.php` |

A Docker service imports `service.php`, plus its own preset when it has one:

```php
// .castor/castor.php (Traefik)
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/service.php');
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/traefik.php');
```

### Structure

Your project should follow this structure:

```tree
project-root/
├── .castor/
│   ├── castor.php            # Import tasks
│   ├── .env.castor           # Castor configuration
│   ├── castor.composer.json  # Package dependencies
│   └── vendor/               # Installed packages
├── compose.yaml              # Base compose file
├── compose.dev.yaml          # Development compose file
├── compose.prod.yaml         # Production compose file
├── compose.app.yaml          # Project-wide layer (committed)
├── compose.override.yaml     # Machine-local layer (gitignored), comments only when empty
└── .env                      # App env file: APP_ENV, COMPOSE_FILE, COMPOSE_PROFILES...
```

## Configuration

**Copy** the environment configuration template from package in `.castor/.env.castor`:

```bash
cp .castor/vendor/tapomix/castor-tasks/dist/.env.dist.castor .castor/.env.castor
```

### Available Configuration Variables

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `APP_CODE_PATH` | `code` | Path to application code in container |
| `APP_FRAMEWORK` | `vanilla` | Framework type (`vanilla`, `symfony`, `laravel`, ...) |
| `APP_SERVICE_DB` | `db` | Database service name in docker-compose |
| `APP_SERVICE_NODE` | `node` | Node service name in docker-compose |
| `APP_SERVICE_PHP` | `php` | PHP service name in docker-compose |
| `CASTOR_DEFAULT_BROWSER` | `firefox` | Default browser executable |
| `INFRA_REMOTE` | `infra` | Git remote used by `infra:sync` and `infra:status` |

### Environment File Loading Order

1. **App environment file** (`.env`, fixed: Compose reads that one)
2. **Castor environment file** (`.castor/.env.castor`)

The app environment file is always loaded first (if it exists), enabling variable interpolation in the Castor config.
It is read as Compose reads it: this single file, no `APP_ENV` default, no `.env.local` / `.env.<env>`. The castor
file only adds variables, it never replaces a `.env` one; a variable exported in the shell wins over both.

**One source of truth:** the app variables are read from the infra names of the `.env` only (`APP_ENV`,
`CONTAINER_NAME`, `DB_NAME`, `DB_USER`, `TRAEFIK_HOST`), the ones Compose uses too: castor and Compose cannot disagree
on the environment or the database. Since 0.6.0 the `APP_*` overrides of these names are ignored.

### Overriding the Default Context

For advanced customization, you can extend the default context in your `.castor/castor.php`:

```php
// .castor/castor.php
<?php

use Castor\Attribute\AsContext;
use Castor\Context;

// ...

#[AsContext(default: true)]
function app_context(): Context
{
    return \Tapomix\Castor\default_context()
        ->withData([
            'APP.FRAMEWORK' => 'symfony',
            'APP.SERVER_NAME' => 'myapp.local',
        ]);
}
```

The default context does not tolerate failures: when a command fails, the task stops and exits non-zero,
wrappers included (`composer`, `console`, `artisan`, `npm`…). A failing task can therefore be caught by a CI,
a git hook or `castor ... && ...`.

## Available Tasks

Tasks are organized by namespace. Click on each section for detailed documentation and examples.

### 🐳 [Docker Tasks](docs/docker.md) (`tapomix-docker`)

Container orchestration, the same commands in the sites and the Docker services (`COMPOSE_FILE` in `.env`).

```bash
castor start   # docker compose up -d, waits for the healthchecks
castor stop    # docker compose down --remove-orphans
castor logs    # docker compose logs -f -t
castor build   # docker compose build --pull
castor pull    # docker compose pull --ignore-buildable
castor shell <service>
```

### 📦 [Composer Tasks](docs/composer.md) (`tapomix-composer`)

Composer command wrappers executed in Docker.

```bash
castor composer <command>
castor composer:dev <command>
castor composer:global <command>
```

### 🔧 Framework-Specific Tasks

#### [Symfony](docs/symfony.md) (`tapomix-symfony`)

Available when `APP_FRAMEWORK=symfony`

```bash
castor symfony:install
castor symfony:install --full
castor console cache:clear
castor console debug:router
castor test
castor test --coverage
```

#### [Laravel](docs/laravel.md) (`tapomix-laravel`)

Available when `APP_FRAMEWORK=laravel`

```bash
castor artisan migrate
castor artisan queue:work
```

### 📊 [Node/NPM Tasks](docs/node.md) (`tapomix-node`)

Node.js and npm command wrappers.

```bash
castor npm <command>
```

### ✅ [QA Tasks](docs/qa.md) (`tapomix-qa`)

Quality assurance and code analysis tools.

```bash
castor qa
castor lint
castor php-cs (--fix)
castor phpstan
castor pint (--fix)
castor rector (--fix)
castor twig-cs (--fix)
```

### 🗄️ [PostgreSQL Tasks](docs/postgres.md) (`tapomix-postgres`)

PostgreSQL database operations.

```bash
castor pg:connect
castor pg:sql "<sql>"
castor pg:backup <timestamp>
castor pg:restore <timestamp>
```

### 🏗️ [Infra Tasks](docs/infra.md) (`tapomix-infra`)

Synchronize frozen files from a shared infrastructure remote, compare them with it, and check pinned versions against the latest releases.

```bash
castor infra:sync
castor infra:sync --remote=upstream --branch=develop
castor infra:status
castor infra:status --remote=infra-local --strict
castor infra:versions
castor infra:versions --strict
```

### 🚀 [VPS Tasks](docs/vps.md) (`tapomix-vps`)

Initialize a freshly cloned site, then update and restart it after new code has been pushed to it.

```bash
castor vps:init
castor vps:deploy
```

### ⚙️ [Service Tasks](docs/service.md) (`tapomix-service`)

Update and restart a Docker service (Traefik, diun...) from its directory, plus the docker tasks above.

```bash
castor service:update
castor check
```

### 🔀 [Traefik Tasks](docs/traefik.md) (`tapomix-traefik`)

Declare the site networks of the Traefik reverse proxy.

```bash
castor traefik:site:add <site> [--puppeteer]
castor traefik:site:puppeteer <site> [--revoke]
castor traefik:site:remove <site>
castor traefik:site:list
```

### 🔐 [DNS/DNSSEC Tasks](docs/dns.md) (`tapomix-dns`)

DNSSEC zone signing and key management.

```bash
castor keys:generate <zone>
castor keys:list <zone>
castor zone:sign <zone>
castor zone:check <zone>
castor zone:verify <zone>
castor dig <args>
```

### 🛠️ [Development Tools](docs/tools.md) (`tapomix-tools`)

Development utilities.

```bash
castor browser
castor password
castor token
```

### ✓ [Check Requirements](docs/check.md) (`tapomix`)

Verify environment and requirements.

```bash
castor check
```

## Development

### Composer Package Management

**IMPORTANT**: PHP and Composer are **not installed** on the host machine. All Composer commands must be executed through Castor tasks that use the `php-qa` Docker container.

```bash
# Install/update dependencies
castor composer install
castor composer update

# Add a package
castor composer require vendor/package

# Any composer command works
castor composer show
castor composer outdated
```

### Quality Assurance

Run all QA tools (PHPStan, Rector, PHP-CS-Fixer) for this repository.

```bash
castor qa
```

### Testing

Run the test suite:

```bash
# Run all tests
castor test

# Run specific test suite
castor test --testsuite=Unit
castor test --testsuite=Integration
```

See [`tests/README.md`](tests/README.md) for detailed testing documentation.
