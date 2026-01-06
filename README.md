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
        "tapomix/castor-tasks": "^0.1"
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
        "tapomix/castor-tasks": "^0.1"
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

use Castor\Helper\PathHelper;

use function Castor\import;

import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/all.php'); // import all tasks
// define('TAPOMIX_APP_ENV_FILE', '.env.docker');
```

**Why not remote-import?** Remote imports change the `workingDirectory` in the context, which we want to avoid.

### Presets

You don't have to load all tasks. The `presets/` directory contains files with only specific task groups. To use a preset, simply replace the `import` line in your `castor.php` (**replace** `<preset>` with one from the list below):

```php
// .castor/castor.php

// ...
import(PathHelper::getCastorVendorDir() . '/tapomix/castor-tasks/presets/<preset>.php');
```

**Available presets:**

| Preset | Description |
| ------ | ----------- |
| `all.php` | All tasks |
| `dns.php` | DNS/DNSSEC tasks only |

### Structure

Your project should follow this structure:

```tree
project-root/
├── .castor/
│   ├── castor.php            # Import tasks
│   ├── .env.castor           # Castor configuration
│   ├── castor.composer.json  # Package dependencies
│   └── vendor/               # Installed packages
├── compose.yaml              # Base compose file (required)
├── compose.dev.yaml          # Development compose file
├── compose.prod.yaml         # Production compose file
├── compose.override.yaml     # Optional override (gitignored)
└── .env(.docker)             # Docker environment variables
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
| `APP_DB_NAME` | `db` | Database name |
| `APP_DB_USER` | `user` | Database user |
| `APP_ENVIRONMENT` | `dev` | Application environment |
| `APP_FRAMEWORK` | `vanilla` | Framework type (`vanilla`, `symfony`, `laravel`, ...) |
| `APP_NAME` | `app` | Application name |
| `APP_SERVER_NAME` | `localhost` | Server hostname |
| `APP_SERVICE_DB` | `db` | Database service name in docker-compose |
| `APP_SERVICE_NODE` | `node` | Node service name in docker-compose |
| `APP_SERVICE_PHP` | `php` | PHP service name in docker-compose |
| `CASTOR_DEFAULT_BROWSER` | `firefox` | Default browser executable |

### Environment File Loading Order

1. **App environment file** (`TAPOMIX_APP_ENV_FILE` constant, default: `.env.docker`)
2. **Castor environment file** (`.castor/.env.castor`)

The app environment file is always loaded first (if it exists), enabling variable interpolation in the Castor config.

**Variable Interpolation:** You can reference variables from your project's main `.env.docker` file using `${VAR}` syntax. This avoids duplication.

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

## Available Tasks

Tasks are organized by namespace. Click on each section for detailed documentation and examples.

### 🐳 [Docker Tasks](docs/docker.md) (`tapomix-docker`)

Container orchestration and management.

```bash
castor build
castor start
castor stop
castor logs
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
castor console cache:clear
castor console debug:router
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

## License

MIT License
