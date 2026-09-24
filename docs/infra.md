# Infra Tasks

Infrastructure tasks for keeping "frozen files" up to date from a shared infra remote, and for checking pinned versions against the latest releases.

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
# Sync using the default remote (INFRA_REMOTE, "infra" if unset) and branch "main"
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
| `--remote` | `-r` | `INFRA_REMOTE` | Git remote name to sync from |
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

### `status` - Compare Frozen Files with Infra Remote

Show the frozen files a sync would change, without touching anything.

**Aliases:** `tapomix-infra:status`, `infra:status`

**Usage:**

```bash
# Compare with the default remote (INFRA_REMOTE, "infra" if unset) and branch "main"
castor infra:status

# Compare with another remote, e.g. a local clone of the template
castor infra:status --remote=infra-local
castor infra:status -r infra-local -b develop

# exit with an error when a frozen file differs (cron, CI)
castor infra:status --strict
```

**Options:**

| Option | Shortcut | Default | Description |
| ------ | -------- | ------- | ----------- |
| `--remote` | `-r` | `INFRA_REMOTE` | Git remote name to compare with |
| `--branch` | `-b` | `main` | Branch to compare with |
| `--strict` | `-s` | `false` | Exit with code 1 when a frozen file differs |

**Output:**

```txt
 --------------- -----------------
  File            State
 --------------- -----------------
  .editorconfig   missing locally
  compose.yaml    differs
 --------------- -----------------
```

**How it works:**

1. Loads the exclusion list and fetches the remote, as `infra:sync` does
2. Reads `.castor/git-frozen-sync.php` **from the remote branch** (`git show`), unless the project excluded it,
   so a file the template just added to the list is reported too; falls back to the local list otherwise
3. Runs `git diff <remote>/<branch> -- <files...>` against the working tree, keeping only the files present on
   the remote: a file the project added under a frozen directory is never touched by a sync, so never reported

> [!NOTE]
> Only what is **committed** on the remote branch is compared: work in progress in the template is ignored.
> Local changes to a frozen file, committed or not, are reported as `differs`, since a sync would overwrite them.

### `versions` - Check Pinned Versions

Compare every pinned version with the latest release **of its branch** and with the latest release overall.
Staying on PHP 8.4 is a choice; missing its latest patch is not.

**Aliases:** `tapomix-infra:versions`, `infra:versions`

**Usage:**

```bash
castor infra:versions

# exit with an error when something calls for an action (cron, CI)
castor infra:versions --strict
castor infra:versions -s
```

**Options:**

| Option | Shortcut | Default | Description |
| ------ | -------- | ------- | ----------- |
| `--strict` | `-s` | `false` | Exit with code 1 when an update is available in a branch, a branch is EOL or a lookup failed |

**Output:**

```txt
 ------------ --------- -------- ------------------ --------- ------------ ------------------
  Component    Current   Branch   Latest in branch   Latest    Branch EOL   Status
 ------------ --------- -------- ------------------ --------- ------------ ------------------
  PHP          8.4.25    8.4      8.4.25             8.5.10    2028-12-31   up to date
  PostgreSQL   18.6      18       18.6               18.6      2030-11-14   up to date
  Symfony      8.1.7     8.1      8.1.7              8.1.7     2027-01-31   up to date
  Alpine.js    3.15.9    3.15     v3.15.12           v3.17.4   —            update available
  Tailwind     v4.3.3    4.3      v4.3.3             v4.3.3    —            up to date
  daisyUI      5.7.28    5.7      v5.7.44            v5.7.44   —            update available
 ------------ --------- -------- ------------------ --------- ------------ ------------------
```

`Latest` is highlighted when it belongs to a newer branch than the current one. `Branch EOL` is highlighted
once the date is past, or less than 90 days away.

**Tracked components:**

| Component | Current version | Branch | Latest version source |
| --------- | --------------- | ------ | --------------------- |
| PHP | `PHP_VERSION` | `8.4` | [endoflife.date](https://endoflife.date/php), then the `dunglas/frankenphp:php<version>` tag on Docker Hub |
| PostgreSQL | `PG_VERSION` | `18` | [endoflife.date](https://endoflife.date/postgresql), then the `postgres:<version>-alpine` tag on Docker Hub |
| Symfony | `symfony/framework-bundle` in `<APP.CODE_PATH>/composer.lock` | `8.1` | [endoflife.date](https://endoflife.date/symfony) |
| Laravel | `laravel/framework` in `<APP.CODE_PATH>/composer.lock` | `12` | [endoflife.date](https://endoflife.date/laravel) |
| Alpine.js | `alpinejs` entry in `<APP.CODE_PATH>/importmap.php` | `3.15` | GitHub releases of `alpinejs/alpine` |
| Tailwind | `TAILWIND_VERSION` | `4.3` | GitHub releases of `tailwindlabs/tailwindcss` |
| daisyUI | `var version` in `<APP.CODE_PATH>/assets/styles/daisyui.mjs` | `5.7` | GitHub releases of `saadeghi/daisyui` |

A component whose current version cannot be read is not shown: a project without daisyUI simply gets no daisyUI
row, and a Symfony project gets no Laravel row.

> [!TIP]
> For the frameworks, `composer update --dry-run` already shows the patches of the branch. What it cannot show is
> the branch EOL — Symfony minors are only maintained for 8 months — nor the next minor, held back by
> `extra.symfony.require`.

**How the current version is read:**

1. The `${XXX_VERSION:-default}` defaults are collected from `compose.yaml`, `compose.app.yaml` and
   `compose.override.yaml`, in that order: a later file overrides an earlier one, as in compose
2. A non-empty value in the app env file (`TAPOMIX_APP_ENV_FILE`, default `.env.docker`) overrides the pin

**Statuses:**

| Status | Meaning | Fails `--strict` |
| ------ | ------- | ---------------- |
| `up to date` | The latest release of the branch is pinned | no |
| `update available` | A newer release exists in the branch | yes |
| `update pending (no image yet)` | A newer release exists, but its Docker image is not published yet | no |
| `EOL soon` | Up to date, but the branch reaches its end of life in less than 90 days | no |
| `branch EOL` | The branch reached its end of life | yes |
| `lookup failed` | The latest version could not be fetched | yes |

> [!NOTE]
> Unauthenticated GitHub API calls are limited to 60 per hour and per IP. Export `GITHUB_TOKEN` to lift the
> limit if the task runs often from a shared IP.

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

Both `infra:sync` and `infra:status` use the `infra` remote by default. To use another one without passing
`--remote` each time, e.g. a local clone of the template whose latest commits are not pushed yet, set it in
`.castor/.env.castor` (owned by the project, never synced):

```bash
git remote add infra-local /path/to/template
```

```dotenv
# .castor/.env.castor
INFRA_REMOTE=infra-local
```

The `--remote` option still takes precedence.

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

# 2. Check what a sync would change, then sync frozen files
castor infra:status
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
| `Unable to compare the frozen files` | The branch does not exist on the remote | Check the `--branch` option |
| `Unable to restore the frozen files` | A path in the frozen list does not exist on the remote branch | Fix the list on the infra remote, or opt out via `.castor/git-frozen-exclude.php` |
