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
> This file is itself part of the frozen files list, and `infra:sync` restores it **before** reading it. A
> `git checkout` is all-or-nothing: a single path that no longer exists on the remote makes git reject the
> whole pathspec, leaving every other frozen file untouched. Reading the remote list first keeps the sync
> self-healing when the template drops a file.

> [!NOTE]
> The sync never deletes anything. Files removed from the frozen list stay in the derived project and must
> be cleaned up by hand.

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

1. Loads the exclusion list from `.castor/git-frozen-exclude.php` (if it exists)
2. Verifies the specified remote exists in the repository
3. Runs `git fetch <remote>` to retrieve the latest state
4. Restores `.castor/git-frozen-sync.php` from the remote, unless the project excluded it
5. Loads that frozen files list and computes the final list: frozen files minus excluded files
6. Runs `git checkout <remote>/<branch> -- <files...>` to restore each frozen file
7. Warns when the sync brought a new `.castor/castor.composer.lock`

> [!IMPORTANT]
> Castor reinstalls its own dependencies when the `content-hash` of `.castor/castor.composer.lock` stops
> matching `.castor/vendor/composer.installed`, and that hash covers `castor.composer.json` alone. A
> template that locks a new version without touching the constraint therefore leaves the castor vendor
> silently stale — same tasks as before, despite the synced lock. When the sync brings a new lock, it
> says so, and the install it asks for must be run:
>
> ```bash
> castor castor:composer install
> ```
>
> Qualify that command. In a derived project the `composer` alias resolves to `tapomix-composer:exec`,
> which runs Composer in the application container against the application code, not on the castor vendor.

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

# 3. Update the castor vendor when the sync warns about a new lock
castor castor:composer install

# 4. Review and commit the updated files
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
| `Unable to fetch from remote "infra"` | The remote is unreachable or the URL is wrong | Check the remote URL and your network access |
| `Unable to restore the frozen files` | A path in the frozen list does not exist on the remote branch | Fix the list on the infra remote, or opt out via `.castor/git-frozen-exclude.php` |
