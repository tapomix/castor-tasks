# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2024-11-19

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
