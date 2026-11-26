# Development Tools

Development utilities for common tasks.

**Namespace:** `tapomix-tools`

## Overview

These tasks provide convenient utilities for development workflows.

## Available Tasks

### `browser` - Open Application in Browser

Open your application in a web browser.

**Aliases:** `tapomix-tools:browser`, `browser`, `open`

**Usage:**

```bash
# Use default browser
castor browser

# Use specific browser
castor browser --browser=firefox
castor browser --browser=chromium
```

**Options:**

- `--browser=<executable>` - Browser executable to use (default: from `CASTOR_DEFAULT_BROWSER`)

**How it works:**

The task opens the URL `https://{TRAEFIK_HOST}` in the specified browser:

```bash
# castor browser
# becomes:
firefox https://localhost &
```

The browser runs in the background and is detached from the process.

---

### `dev` - Start and Open the Application

Start all services, then open the application in the browser: `castor start -u && castor open` in one command.

**Aliases:** `tapomix-tools:dev`, `dev`

**Usage:**

```bash
# Pull and build the images, start, open the browser
castor dev

# Skip the pull and the build
castor dev --no-update

# Use specific browser
castor dev --browser=chromium
```

**Options:**

- `-u, --update` / `--no-update` - Pull and build the images first (default: enabled)
- `--browser=<executable>` - Browser executable to use (default: from `CASTOR_DEFAULT_BROWSER`)

**How it works:**

Runs `start` (with `--update` unless `--no-update`), then `browser`. A failing start stops the task: the browser never
opens on a stack that did not come up.

---

### `password` - Generate Secure Password

Generate a cryptographically secure random password.

**Aliases:** `tapomix-tools:password`, `password`

**Usage:**

```bash
# Generate password with default length (16 characters)
castor password

# Generate password with custom length
castor password --length=32
castor password -l 20
```

**Options:**

- `-l, --length=<number>` - Password length (default: 16, minimum: 12)

**How it works:**

The password generator:

- Ensures at least one character from each category: lowercase, uppercase, digit, special character
- Uses `random_int()` for every pick and for the shuffle (Fisher-Yates): cryptographically secure, unbiased
- Available special characters: `!&*-_+=` — safe in a `.env` and in a URL: no `$` (Compose interpolation), `%`
  (encoding, Symfony `%env(resolve:)%`), `@ # ?` (`DATABASE_URL` delimiters)

For a secret read by programs only (`DB_PASS`, `APP_SECRET`), prefer `castor token`: hexadecimal, longer, nothing to
escape anywhere.

**Example output:**

```txt
Copy+Paste your new password : aB3$xY9*mN2!pQ8&
```

---

### `token` - Generate Random Token

Generate a random hexadecimal token for API keys, secrets, etc.

**Aliases:** `tapomix-tools:token`, `token`

**Usage:**

```bash
# Generate token with default length (32 characters)
castor token

# Generate token with custom length
castor token --length=64
castor token -l 16
```

**Options:**

- `-l, --length=<number>` - Token length (default: 32, minimum: 4)

**How it works:**

The token generator uses `random_bytes()` and converts to hexadecimal format for a cryptographically secure token.

**Example output:**

```txt
Copy+Paste your new token : a3f8d92b4e6c1a7f5d8e2b9c4a6f3e1d
```

---

## Configuration

### Default Browser

Configure the default browser in `.castor/.env.castor`:

```bash
# Set default browser
CASTOR_DEFAULT_BROWSER=firefox
# or
CASTOR_DEFAULT_BROWSER=chromium
```

### Server Name

The browser task uses the application server name, read from `TRAEFIK_HOST` in the `.env`.

### Context Variables Used

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `APP.SERVER_NAME` | `localhost` | Server hostname for browser task |
| `CASTOR.DEFAULT_BROWSER` | `firefox` | Default browser executable |

---
