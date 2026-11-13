# VPS Tasks

Initialize a site freshly cloned on the VPS, then update and restart it after new code has been pushed to it.

**Namespace:** `tapomix-vps`

## Concept

Apps deployed with this package typically live on the VPS as a git checkout: a bare repo receives pushes from local development, and a working copy on the VPS runs the app via Docker Compose. Getting a push to actually run requires a manual sequence — `git pull`, update the code submodule (if any), rebuild the images, restart the containers, run pending migrations.

The `vps:deploy` task automates that sequence into a single command, run **on the VPS**, after code has already been pushed there.

> [!NOTE]
> The `vps:` prefix is deliberate: it distinguishes tasks that run *on* the VPS from the act of pushing code *to* the VPS (a plain `git push` to your VPS remote, a separate step not covered by this package).

## Available Tasks

### `init` - Initialize a Freshly Cloned Site

Bring a site checkout to a deployable state: create what is missing, never overwrite, report every item. Run it again
until everything is ✔ — the output is the to-do list; the task exits non-zero while an item needs you.

**Aliases:** `vps:init`

```bash
castor vps:init
```

| Item | When absent | When present |
| --- | --- | --- |
| `.env` | copied from `.env.dist`, prefilled (below) | checked: `CONTAINER_NAME` set, `APP_ENV=prod`, `TRAEFIK_HOST` with a domain (not `domain.ext`), `DB_PASS` not `ChangeMe!` |
| `<APP_CODE_PATH>/` | reported: `git submodule update --init` (an uninitialized submodule leaves it empty) | ✔ |
| `.castor/.env.castor` | copied from `.env.castor.dist`, `APP_FRAMEWORK` detected — waits for the code | ✔ |
| `compose.override.yaml` | written, comments only | ✔ |
| `.docker/.composer-auth.json` | `{}` | ✔ |
| `<APP_CODE_PATH>/.env.prod.local` | Symfony only: a generated `APP_SECRET` — add the project prod values by hand | checked: `APP_SECRET` set and not empty |
| `COMPOSE_FILE` | — | validated as the docker tasks do |
| Network `traefik-<CONTAINER_NAME>` | reported: declare the site in Traefik | ✔ |

**Prefill from the VPS layout.** Sites live in `<web>/<domain>/<site>`, served at `https://<site>.<domain>`; on
creation, `.env` gets `CONTAINER_NAME=<site>`, `APP_ENV=prod`, `TRAEFIK_HOST=${CONTAINER_NAME}.<domain>` and a
generated `DB_PASS`. The values are displayed: a site cloned elsewhere gets wrong ones, visible there and caught by
the network check.

**Framework detection.** `<APP_CODE_PATH>/bin/console` → `symfony`, `<APP_CODE_PATH>/artisan` → `laravel`, otherwise
`vanilla`.

**Two env files.** `.env` at the root serves the infra (Compose, castor); `code/.env.prod.local` serves the Symfony app
(compiled by `composer dump-env prod` in the deploy script, which also fixes its `APP_ENV`).

**Secrets.** `DB_PASS` and `APP_SECRET` are hex tokens (`generateToken()`): nothing that breaks a `.env` or the
`DATABASE_URL`. The files holding them (`.env`, `.env.prod.local`, `.composer-auth.json`) are created readable by
their owner only (`0600`).

**Enabled** while `APP_ENV` is unset (a fresh clone, no `.env` yet) or `prod`; hidden on a dev machine.

---

### `deploy` - Deploy Latest Code on the VPS

Pull the latest code, rebuild and restart the containers, and run pending migrations.

**Aliases:** `vps:deploy`

**Usage:**

```bash
castor vps:deploy
```

**Behavior:**

1. `git pull` on the app's root repo
2. `castor castor:composer install` — only when the pull brought a new `.castor/castor.composer.lock`
3. `git submodule update --init --recursive` — only if the project uses a submodule (`.gitmodules` present); skipped when the app code lives directly at the repo root
4. Pull the non-buildable images (equivalent to `castor pull`)
5. Rebuild all Docker images, base images pulled (equivalent to `castor build`), after creating
   `.docker/.composer-auth.json` (`{}`) when absent: a build secret of `compose.prod.yaml`
6. Restart all containers (equivalent to `castor stop` then `castor start`)
7. Run pending migrations, based on `APP.FRAMEWORK`:
   - Symfony → `doctrine:migrations:migrate --no-interaction`
   - Laravel → `artisan migrate --force`
   - Other frameworks → step skipped

Castor does not reinstall its own dependencies when only the lock changes (a new castor-tasks version under the same
constraint), hence step 2. The run in progress already loaded the previous tasks: the new ones apply from the next run.
A failing install (network, GitHub) does not block the deployment: the task warns, with the command to run by hand,
and goes on.

The task stops at the first failing step and exits non-zero: a failed `git pull`, build or migration
never ends with a restarted app reported as deployed.

**Requires:** `APP_ENV=prod` in the `.env` — the task is only enabled when running against a production environment, to prevent an accidental run against a local/dev setup.

---

## Workflow Example

```bash
# 0. A new site on the VPS (see the skeleton README: site-clone)
castor vps:init      # until everything is ✔

# 1. Local development: push new commits to the VPS remote
git push vps main

# 2. On the VPS: pull the new code, rebuild, restart, migrate
castor vps:deploy
```
