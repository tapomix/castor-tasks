# DNS Tasks

DNSSEC zone signing and key management tasks.

**Namespace:** `tapomix-dns`

## Prerequisites

Your project must follow this directory structure:

```tree
.docker/server/
├── keys/                    # DNSSEC keys (KSK + ZSK)
│   ├── Kexample.com.+015+12345.key
│   ├── Kexample.com.+015+12345.private
│   └── ...
└── zones/
    ├── raw/                 # Source zone files with __SERIAL__ placeholder
    │   └── example.com.zone
    ├── unsigned/            # Zone files with computed serial (generated)
    │   └── example.com.zone
    └── signed/              # Signed zone files (generated)
        └── example.com.zone.signed
```

The tasks use a Docker container with BIND tools (`dnssec-keygen`, `dnssec-signzone`, `dnssec-verify`, `named-checkzone`, `dig`).

## Available Tasks

### `sign` - Sign a DNS Zone

Sign a DNS zone with DNSSEC. This is the main task for zone signing.

**Aliases:** `tapomix-dns:sign`, `zone:sign`

**Usage:**

```bash
castor zone:sign example.com
```

**Arguments:**

- `zone` - The zone name to sign (e.g., `example.com`)

**How it works:**

1. Reads the raw zone file from `zones/raw/`
2. Computes the next serial number (format: `YYYYMMDDVV`)
3. Replaces `__SERIAL__` placeholder with the computed serial
4. Writes the unsigned zone to `zones/unsigned/`
5. Validates syntax with `named-checkzone`
6. Signs the zone with `dnssec-signzone` using existing keys
7. Verifies signatures with `dnssec-verify`
8. Outputs the signed zone to `zones/signed/`

---

### `check` - Check Zone Syntax

Validate zone file syntax using `named-checkzone`.

**Aliases:** `tapomix-dns:check`, `zone:check`

**Usage:**

```bash
castor zone:check example.com
```

**Arguments:**

- `zone` - The zone name to check

---

### `verify` - Verify DNSSEC Signatures

Verify DNSSEC signatures on a signed zone file using `dnssec-verify`.

**Aliases:** `tapomix-dns:verify`, `zone:verify`

**Usage:**

```bash
castor zone:verify example.com
```

**Arguments:**

- `zone` - The zone name to verify

---

### `generate` - Generate DNSSEC Keys

Generate a new KSK (Key Signing Key) and ZSK (Zone Signing Key) pair for a zone.

**Aliases:** `tapomix-dns:generate`, `keys:generate`

**Usage:**

```bash
# Generate keys with default algorithm (ED25519)
castor keys:generate example.com

# Generate keys with specific algorithm
castor keys:generate example.com -a 13
castor keys:generate example.com --algorithm=15
```

**Arguments:**

- `zone` - The zone name

**Options:**

- `-a, --algorithm=<number>` - DNSSEC algorithm (default: 15 = ED25519)

**Supported Algorithms:**

| Value | Name | Description |
| ----- | ---- | ----------- |
| 8 | RSASHA256 | RSA with SHA-256 |
| 10 | RSASHA512 | RSA with SHA-512 |
| 13 | ECDSAP256SHA256 | ECDSA P-256 with SHA-256 |
| 14 | ECDSAP384SHA384 | ECDSA P-384 with SHA-384 |
| **15** | **ED25519** | Edwards-curve 25519 (*recommended*) |
| 16 | ED448 | Edwards-curve 448 |

---

### `listing` - List DNSSEC Keys

List all DNSSEC keys for a zone with their details and DS records.

**Aliases:** `tapomix-dns:listing`, `keys:list`

**Usage:**

```bash
# List all keys
castor keys:list example.com

# List only KSK keys (for registrar import)
castor keys:list example.com -k
castor keys:list example.com --only-ksk
```

**Arguments:**

- `zone` - The zone name

**Options:**

- `-k, --only-ksk` - List only KSK keys

**Output includes:**

- Key tag
- Key type (KSK/ZSK) with flag value
- Algorithm
- Private key status (OK/KO)
- Public key (base64)
- DS records for registrar configuration

---

### `query` - Execute DiG Command

Execute a DiG (Domain Information Groper) command in the DNS tools container.

**Aliases:** `tapomix-dns:query`, `dig`

**Usage:**

```bash
# Query A record
castor dig example.com A

# Query with specific nameserver
castor dig @8.8.8.8 example.com AAAA

# Query DNSSEC records
castor dig example.com DNSKEY +dnssec
castor dig example.com DS
```

**Arguments:**

- `args` - DiG command arguments (passed directly to dig)

---

## Serial Number Format

The tasks use the standard DNS serial format: `YYYYMMDDVV`

- `YYYYMMDD` - Current date
- `VV` - Version number for the day (01-99)

**Examples:**

- `2025010501` - First signature on January 5, 2025
- `2025010502` - Second signature on the same day
- `2025010601` - First signature on January 6, 2025

The serial is automatically incremented based on the current signed zone file.

---

## Workflow Example

```bash
# 1. Generate keys (first time only)
castor keys:generate example.com

# 2. List keys to get DS record for registrar
castor keys:list example.com -k

# 3. Sign the zone
castor zone:sign example.com

# 4. Verify DNS propagation
castor dig example.com DNSKEY +dnssec
```

---
