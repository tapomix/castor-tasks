<!-- markdownlint-disable MD024 -->
# Changelog

All notable changes to this project will be documented in this file.

---

## [Unreleased]

### Changed

- `composer` removes the final newline Composer writes in `composer.json` when the `[*.json]` section of the app's
  `.editorconfig` sets `insert_final_newline = false`

- `build` and `service:update` skip the build of an image-only project instead of the Compose warning
  "No services to build"

---

## [0.6.1] - 2026-11-13

### Added

- `vps:init` to initialize a freshly cloned site on the VPS: creates what is missing (`.env` prefilled from the
  `<web>/<domain>/<site>` layout, `.castor/.env.castor` with the detected framework, `compose.override.yaml`,
  `.docker/.composer-auth.json`, the Symfony `APP_SECRET`, `USER_ID` / `GROUP_ID` of the VPS user), never overwrites,
  reports every item; secret files `0600`

### Changed

- An unset `APP_ENV` is its own state (`TAPOMIX_ENV_UNDEFINED`), no longer `dev`: the dev tasks are hidden and a site
  is refused until `APP_ENV` is set

### Fixed

- `generatePassword()` no longer uses `@ # $ % ?`, which broke a `.env` or a `DATABASE_URL`, and picks and shuffles
  with `random_int()`

---

## [0.6.0] - 2026-11-13

### Added

- The `service` preset brings the docker tasks: `start`, `stop`, `logs`, `build`, `pull`, `shell` in every service
- `pull` alias for `tapomix-docker:pull`
- `check` validates `COMPOSE_FILE`
- `vps:deploy` creates `.docker/.composer-auth.json` before the build

### Changed

- **Breaking:** the docker tasks run a plain `docker compose` from the project root: the compose files come from
  `COMPOSE_FILE` in `.env`, no more `-f`, `--profile` or `--env-file`. `COMPOSE_FILE` is required everywhere; a site
  must set exactly `compose.yaml:compose.${APP_ENV}.yaml:compose.app.yaml:compose.override.yaml` and
  `COMPOSE_PROFILES=${APP_ENV}`, and `compose.override.yaml` must exist (comments only when empty)
- **Breaking:** the app env file is `.env`, fixed: `TAPOMIX_APP_ENV_FILE` and `DOCKER.ENV_FILE` are gone
- **Breaking:** `.env` is read as Compose reads it: no `APP_ENV` default (a site without it is refused), no
  `.env.local` / `.env.<env>`; `.castor/.env.castor` only adds variables, it never replaces a `.env` one
- **Breaking:** the `APP_ENVIRONMENT`, `APP_NAME`, `APP_DB_NAME`, `APP_DB_USER` and `APP_SERVER_NAME` overrides are
  ignored: the infra names of `.env` only
- `getContainerPhpVersion()` moved to the composer helpers
- `service:update` validates `COMPOSE_FILE` through the docker tasks

### Migration

A site:

1. `infra:sync` from the template, review, commit
2. `castor castor:composer update` (`^0.6`), commit the lock
3. Locally and on the VPS: add the `COMPOSE_FILE` line to `.env` (from `.env.dist`), create `compose.override.yaml`
   when absent; a project still on the old `.env.docker` default renames it `.env`
4. Optional: drop the `APP_*` lines of `.castor/.env.castor`
5. On the VPS, after step 3: `castor vps:deploy`. The running version keeps its `-f` flags, which override
   `COMPOSE_FILE`; the constraint change makes castor reinstall its vendor on the next run

A Docker service: `COMPOSE_FILE` in `.env`, `^0.6`, then `castor service:update` on the VPS.

---

## [0.5.2] - 2026-11-13

### Added

- `service:update` and `vps:deploy` install the castor tasks when the pull brought a new castor lock, a failing install only warns

### Changed

- The castor lock helpers are shared in `src/castor-lock.php`, loaded by each task that needs them

---

## [0.5.1] - 2026-11-13

### Added

- `--puppeteer` option on `traefik:site:add`, and `traefik:site:puppeteer` (`--revoke`) to grant or revoke the Puppeteer access of a site
- `compose.sites.yaml` explains its networks in its generated header

### Changed

- **Breaking:** the `puppeteer` alias is only set on the sites that use Puppeteer, no longer on every site.
  A 0.5.0 file keeps its aliases until a site is revoked
- `traefik:site:list` shows the Puppeteer access of each site

### Fixed

- Install examples in the README require `^0.5`
- `php.php` preset described as without `npm`
- The site name is checked before reading `compose.sites.yaml`

---

## [0.5.0] - 2026-11-13

### Added

- `service:update` to update a Docker service (pull, build, recreate), relying on `COMPOSE_FILE`
- `traefik:site:add|remove|list` to manage the Traefik site networks in `compose.sites.yaml`
- `service` and `traefik` presets
- `service:update` stops when `COMPOSE_FILE` is not set

### Changed

- App variables read from the infra names by convention (`APP_ENV`, `CONTAINER_NAME`...), `APP_*` as overrides
- `check` is part of the `php` and `service` presets
- `check` no longer requires `.castor/.env.castor`
- Updated Composer dependencies

### Removed

- **Breaking:** the `all` preset, switch to `presets/php.php`

---

## [0.4.2] - 2026-11-06

### Changed

- `logs` shows the timestamp of each log line
- Updated Composer dependencies

---

## [0.4.1] - 2026-10-23

### Changed

- **Breaking:** PHP 8.4 is now required, like Castor itself since 1.6
- Updated Composer dependencies (Castor 1.9, PHPUnit 13)

---

## [0.4.0] - 2026-10-23

### Added

- `--update` (`-u`) option on `start` to pull and build the images first: only the containers whose image changed are recreated
- `--no-pull` option on `build`

### Changed

- **Breaking:** the default context no longer tolerates failures: a failing command stops the task and exits non-zero,
  wrappers included (`composer`, `console`, `artisan`, `npm`…). A custom task handling a failure must pass `context()->withAllowFailure()`
- `build` pulls the base images by default, so a republished tag is picked up
- `vps:deploy` pulls the non-buildable images before building, and stops at the first failing step
- Updated Composer dependencies

### Fixed

- `symfony:test` exited `0` on a failing or crashing suite: it now exits with PHPUnit's exit code
- QA tasks exited `0` when an analyzer reported errors: each task now exits with its tool's exit code, and `qa` with the highest one while still running every analyzer

---

## [0.3.9] - 2026-10-20

### Changed

- `symfony:install` aligns `require.php` with the container PHP version (major.minor)
- Updated Composer dependencies

---

## [0.3.8] - 2026-09-25

### Added

- `infra:versions` task to check pinned versions against the latest releases
- `infra:status` task to list frozen files differing from the infra remote
- `INFRA_REMOTE` variable to set the default infra remote

### Changed

- `infra:sync` no longer prints the `git fetch` output
- Updated Composer dependencies

### Fixed

- Castor 1.8 deprecation: define `CASTOR_USE_CHDIR` (see README)

---

## [0.3.7] - 2026-09-11

### Added

- `symfony:test` task to run the PHPUnit suite in Docker, with `--filter` and `--coverage` options
- `$env` parameter on `Docker\exec()` to pass extra `-e KEY=VALUE` variables to a single call
- `symfony:install` now removes recipe files superseded by the project-level configuration

### Changed

- `infra:sync` now warns when the sync brings a new Castor dependency lock
- Boolean options harmonized to a single non-negatable flag, dropping `--no-*` variants

---

## [0.3.6] - 2026-09-02

### Fixed

- `infra:sync` restored nothing at all when the frozen list named a file the template had dropped: the list is now refreshed from the remote before being read
- `infra:sync` reported a successful sync even when `git fetch` or `git checkout` had failed

---

## [0.3.5] - 2026-08-26

### Added

- Support for `compose.app.yaml`, an optional project-wide Compose layer, committed and loaded after the environment file
- `--profile <APP_ENVIRONMENT>` on every Docker task, to gate services per environment
- Documentation for the Compose layer order and profiles in `docs/docker.md`

### Changed

- `declare(strict_types=1)` in every source file, now enforced by PHP-CS-Fixer
- Removed deprecated rules from the Rector configuration
- Updated Composer dependencies

### Fixed

- Castor tasks died on start when no controlling terminal was available (Git hooks, CI, coding agents)
- `composer` in the QA image resolved to the vendored copy, destroying the autoload it ran from

---

## [0.3.4] - 2026-08-08

### Added

- VPS tasks namespace (`tapomix-vps`) with `vps:deploy` task
- Documentation for the `vps` namespace
- `infra.php` and `php.php` presets

### Changed

- `symfony:install` now passes `--json` and `--no-interaction` to `composer` calls

### Fixed

- Use `.docker/db/dump` instead of `.docker/db/sql` for local SQL dumps

---

## [0.3.3] - 2026-03-27

### Added

- Aliases `symfony:console` and `symfony:install` for Symfony-related tasks

### Changed

- `symfony:install` now configures Composer automatically

---

## [0.3.2] - 2026-03-27

### Added

- `symfony:install` task to bootstrap a new Symfony project
- Documentation for `symfony:install` in `docs/symfony.md`

### Fixed

- `infra:sync` now detects new files added to the frozen list during sync
- PHPStan errors in DNS `_tools.php`

### Changed

- Extracted `checkoutFiles()` helper in `infra:sync` to avoid duplicated calls

---

## [0.3.1] - 2026-03-19

### Added

- Infrastructure tasks namespace (`tapomix-infra`) with `infra:sync` task to sync frozen files from a Git remote
- Unit tests for `infra:sync`
- Documentation for the `infra` namespace

### Changed

- Updated Composer dependencies
- Renamed interpolated server name variable in `.env.dist.castor`
- Reorganized `.gitignore` and added frozen files to ignore list

---

## [0.3.0] - 2026-01-06

### Added

- DNS tasks namespace (`tapomix-dns`) with DiG queries, DNSSEC key generation, and zone management
- Unit tests for DNS utilities
- Shortcut `-l` for `--length` option on password and token generators
- Presets system for task grouping (`all`, `dns`)

---

## [0.2.0] - 2025-12-17

### Added

- Environment-based conditional expressions (`EXPR_ENV_DEV`, `EXPR_ENV_PROD`)
- Optional `shell` parameter for `docker:shell` task (defaults to `bash`)
- Argument validation for `composer:dev` and `composer:global` tasks

### Changed

- Renamed `listTools()` to `listAnalyzers()` in QA module for better clarity
- Added `runCastorCommand()` helper function to execute Castor commands by name
- Moved distribution files to `dist/` directory (`castor.dist.php`, `.env.dist.castor`)
- QA tasks now only enabled in development environment (`APP.ENVIRONMENT === 'dev'`)
- Browser tool now only enabled in development environment
- Replaced `array_merge()` with spread operator (`...`) for better performance and readability
- Renamed `docker:shell` alias from `sh` to `shell` to avoid confusion (default shell is `bash`, not `sh`)

### Fixed

- Fixed local file paths resolution
- Fixed QA analyzers listing to properly filter and execute commands

---

## [0.1.0] - 2025-11-19

Initial release.

### Added

- Context-based configuration system with `.castor/.env.castor` file
- Environment variable interpolation from app's `.env` file
- Docker orchestration tasks (build, start, stop, logs, shell)
- Composer command wrappers for Docker execution
- Framework-specific tasks for Symfony and Laravel
- Node/NPM task wrappers
- QA tools integration (Lint, PHPStan, Rector, PHP-CS-Fixer, Pint, Twig-CS-Fixer)
- PostgreSQL database operations (connect, backup, restore, SQL execution)
- Development utilities (browser opener, password/token generators)
- Environment requirements checker
- Unit tests for database validation helpers + generators
- Complete documentation (README + individual task namespace docs + tests)
- Castor tasks for local development (composer + qa + test)

---

## About

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).  
The changelog format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
