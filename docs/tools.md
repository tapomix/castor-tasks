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

The task opens the URL `https://{APP_SERVER_NAME}` in the specified browser:

```bash
# castor browser
# becomes:
firefox https://localhost &
```

The browser runs in the background and is detached from the process.

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
castor password --length=20
```

**Options:**

- `--length=<number>` - Password length (default: 16, minimum: 12)

**How it works:**

The password generator:

- Ensures at least one character from each category: lowercase, uppercase, digit, special character
- Uses `random_bytes()` for cryptographic security
- Shuffles the final result
- Available special characters: `!@#$%&*-_+=?`

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
castor token --length=16
```

**Options:**

- `--length=<number>` - Token length (default: 32, minimum: 4)

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

The browser task uses the application server name:

```bash
# Set server hostname
APP_SERVER_NAME=localhost
# or
APP_SERVER_NAME=myapp.local
```

### Context Variables Used

| Variable | Default | Description |
|----------|---------|-------------|
| `APP.SERVER_NAME` | `localhost` | Server hostname for browser task |
| `CASTOR.DEFAULT_BROWSER` | `firefox` | Default browser executable |

---
