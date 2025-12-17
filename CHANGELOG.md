<!-- markdownlint-disable MD024 -->
# Changelog

All notable changes to this project will be documented in this file.

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
