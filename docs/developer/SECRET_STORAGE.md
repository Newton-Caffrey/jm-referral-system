# Secret Storage (Phase 5C.1)

Encrypted credential vault for mailbox connection secrets. Keys never live in the WordPress database.

| | |
| --- | --- |
| **Phase** | 5C.1 |
| **Database** | 2.32.0 (`jmrs_mailbox_connection_secrets`) |
| **Algorithm** | libsodium XChaCha20-Poly1305 IETF AEAD |
| **Key source** | Dedicated wp-config / environment constants |

Related: [`MICROSOFT_365_CONNECTION.md`](MICROSOFT_365_CONNECTION.md).

---

## What is stored

Table `{prefix}jmrs_mailbox_connection_secrets` stores:

- `connection_id`
- `secret_name` (initially `client_secret`)
- `algorithm`
- `key_version`
- `nonce` (Base64)
- `ciphertext` (Base64)
- timestamps

**Unique:** `(connection_id, secret_name)`.

**Never store:** plaintext client secret, access token, refresh token, mailbox password, encryption keys.

**Important:** Client secret **ciphertext ≠ access token**. Application (client-credentials) auth does not require refresh-token persistence. Token acquisition belongs to later phases; access tokens should remain short-lived runtime material.

---

## Encryption key configuration

Administrators generate a 32-byte random key **outside** the repository and place it in `wp-config.php`.

Example generation command (run locally; do not commit the output):

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Then configure:

```php
define('JMRS_SECRET_ACTIVE_KEY_VERSION', 1);
define('JMRS_SECRET_KEY_V1', '<base64 encoded 32-byte key>');
```

Rules:

- Active version must exist and be ≥ 1
- Matching `JMRS_SECRET_KEY_V{n}` must exist
- Value must Base64-decode **strictly** to **exactly 32 bytes**
- **No fallback** to `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, or any WordPress salt
- Do **not** commit real keys, put keys in docs examples, fixtures, logs, or the database

---

## Key validation (fail closed)

`SecretKeyProvider` statuses:

| Status | Admin UI label |
| --- | --- |
| `ready` | Ready |
| `key_missing` | Key missing / Encryption key not configured |
| `key_invalid` | Key invalid / Encryption key configuration invalid |
| `unavailable` | Secure encryption unavailable |

If encryption cannot succeed, first save **must not** create a partially configured active Microsoft connection.

---

## Algorithm and AAD

Preferred functions:

- `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt()`
- `sodium_crypto_aead_xchacha20poly1305_ietf_decrypt()`

Fresh cryptographically random nonce per encryption. Nonce/key combinations must not be reused.

Associated data (AAD) binds ciphertext to:

```text
jmrs|{connection_id}|{secret_name}|{key_version}
```

If required sodium functions are unavailable: **fail closed**. Never store plaintext.

---

## Key versioning / rotation foundation

Each secret row stores `key_version`.

- **Encrypt** with `JMRS_SECRET_ACTIVE_KEY_VERSION`
- **Decrypt** with `JMRS_SECRET_KEY_V{stored key_version}`

This allows future rotation without schema redesign.

**Future rotation procedure (conceptual — no UI in 5C.1):**

1. Generate a new 32-byte key; define `JMRS_SECRET_KEY_V2`
2. Set `JMRS_SECRET_ACTIVE_KEY_VERSION` to `2`
3. Re-encrypt each secret under v2 (tooling TBD)
4. Retain old `JMRS_SECRET_KEY_V1` until all rows use v2
5. Remove old key constant only after verification

---

## Service API

| Operation | Behaviour |
| --- | --- |
| `storeSecret` | Encrypt + upsert ciphertext |
| `hasSecret` | Boolean presence |
| `retrieveSecret` | Runtime plaintext only (not for templates) |
| `deleteSecret` / delete-all-for-connection | Remove ciphertext rows |

Plaintext exists in memory only for the shortest practical time. Repositories never return plaintext to admin templates.

Safe error codes include: `secret_unavailable`, `secret_key_missing`, `secret_decryption_failed`. Cryptographic exception internals are not exposed to normal UI.

---

## Classes

| Class | Role |
| --- | --- |
| `SecretKeyProvider` | Versioned key load + validation |
| `SecretCipher` | AEAD encrypt/decrypt |
| `MailboxConnectionSecretRepository` | Persistence |
| `MailboxConnectionSecretService` | Application boundary |
