# Infra Tasks

Infrastructure synchronization tasks for keeping "frozen files" up to date from a shared infra remote.

**Namespace:** `tapomix-infra`

## Concept

Some files in a project are **frozen** — they are owned and maintained by a shared infrastructure template (infra remote) and should not be modified manually in derived projects. The `infra:sync` task pulls the latest version of those files directly from the infra Git remote, overwriting any local changes.

Two configuration files control which files are synchronized:

| File | Owner | Purpose |
| ---- | ----- | ------- |
| `.castor/git-frozen-sync.php` | Template / infra remote | Declares the list of frozen files to sync |
| `.castor/git-frozen-exclude.php` | Derived project | Opts out of specific frozen files (optional) |

## Configuration Files

### `.castor/git-frozen-sync.php`

This file is **provided and maintained by the infra remote**. It returns a PHP array of file paths (relative to the project root) that must be kept in sync.

```php
// .castor/git-frozen-sync.php
<?php

return [
    'docker-compose.yml',
    '.docker/php/Dockerfile',
    '.github/workflows/ci.yml',
];
```

> [!IMPORTANT]
> This file itself is typically part of the frozen files list, meaning it is updated during sync.

### `.castor/git-frozen-exclude.php`

This file is **optional** and defined by the derived project. It returns a PHP array of file paths to exclude from the sync. Use it to opt out of specific frozen files that your project overrides intentionally.

```php
// .castor/git-frozen-exclude.php
<?php

return [
    '.github/workflows/ci.yml', // custom CI pipeline for this project
];
```

## Available Tasks

### `sync` - Sync Frozen Files from Infra Remote

Fetch frozen files from the infra remote and restore them via `git checkout`.

**Aliases:** `tapomix-infra:sync`, `infra:sync`

**Usage:**

```bash
# Sync using default remote "infra" and branch "main"
castor infra:sync

# Sync from a specific remote
castor infra:sync --remote=upstream
castor infra:sync -r upstream

# Sync from a specific branch
castor infra:sync --branch=develop
castor infra:sync -b develop

# Combine options
castor infra:sync -r upstream -b develop
```

**Options:**

| Option | Shortcut | Default | Description |
| ------ | -------- | ------- | ----------- |
| `--remote` | `-r` | `infra` | Git remote name to sync from |
| `--branch` | `-b` | `main` | Branch to sync from |

**How it works:**

1. Loads the frozen files list from `.castor/git-frozen-sync.php`
2. Loads the exclusion list from `.castor/git-frozen-exclude.php` (if it exists)
3. Computes the final list: frozen files minus excluded files
4. Verifies the specified remote exists in the repository
5. Runs `git fetch <remote>` to retrieve the latest state
6. Runs `git checkout <remote>/<branch> -- <files...>` to restore each frozen file

---

## Setup

### 1. Add the infra remote

```bash
git remote add infra <infra-repo-url>
```

To prevent accidental pushes to the infra remote:

```bash
git remote set-url --push infra NO_PUSH
```

### 2. Create the frozen files list

Either copy it from the infra remote after the first sync, or create it manually:

```php
// .castor/git-frozen-sync.php
<?php

return [
    // list files managed by the infra remote
];
```

### 3. (Optional) Create the exclusion list

```php
// .castor/git-frozen-exclude.php
<?php

return [
    // list files your project manages independently
];
```

---

## Workflow Example

```bash
# 1. Add the infra remote (once)
git remote add infra https://git.example.com/infra/template.git
git remote set-url --push infra NO_PUSH

# 2. Sync frozen files
castor infra:sync

# 3. Review and commit the updated files
git diff
git add .
git commit -m "chore: sync frozen files from infra"
```

---

## Error Cases

| Error | Cause | Solution |
| ----- | ----- | -------- |
| `Frozen files list not found` | `.castor/git-frozen-sync.php` is missing | Create the file or sync it manually from the infra remote |
| `No files to sync after applying exclusions` | All frozen files are excluded | Review `.castor/git-frozen-exclude.php` |
| `Remote "infra" not found` | The Git remote is not configured | Run `git remote add infra <url>` |
