# VPS Tasks

Update and restart the app on the VPS after new code has been pushed to it.

**Namespace:** `tapomix-vps`

## Concept

Apps deployed with this package typically live on the VPS as a git checkout: a bare repo receives pushes from local development, and a working copy on the VPS runs the app via Docker Compose. Getting a push to actually run requires a manual sequence — `git pull`, update the code submodule (if any), rebuild the images, restart the containers, run pending migrations.

The `vps:deploy` task automates that sequence into a single command, run **on the VPS**, after code has already been pushed there.

> [!NOTE]
> The `vps:` prefix is deliberate: it distinguishes tasks that run *on* the VPS from the act of pushing code *to* the VPS (a plain `git push` to your VPS remote, a separate step not covered by this package).

## Available Tasks

### `deploy` - Deploy Latest Code on the VPS

Pull the latest code, rebuild and restart the containers, and run pending migrations.

**Aliases:** `vps:deploy`

**Usage:**

```bash
castor vps:deploy
```

**Behavior:**

1. `git pull` on the app's root repo
2. `git submodule update --init --recursive` — only if the project uses a submodule (`.gitmodules` present); skipped when the app code lives directly at the repo root
3. Rebuild all Docker images (equivalent to `castor build`)
4. Restart all containers (equivalent to `castor stop` then `castor start`)
5. Run pending migrations, based on `APP.FRAMEWORK`:
   - Symfony → `doctrine:migrations:migrate --no-interaction`
   - Laravel → `artisan migrate --force`
   - Other frameworks → step skipped

**Requires:** `APP_ENVIRONMENT=prod` — the task is only enabled when running against a production environment, to prevent an accidental run against a local/dev setup.

---

## Workflow Example

```bash
# 1. Local development: push new commits to the VPS remote
git push vps main

# 2. On the VPS: pull the new code, rebuild, restart, migrate
castor vps:deploy
```
