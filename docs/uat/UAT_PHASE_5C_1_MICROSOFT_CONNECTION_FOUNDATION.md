# UAT — Phase 5C.1 Microsoft 365 Connection Foundation

**Product:** 1.5.0 · **DB:** 2.32.0 · **Rewrite:** 1.2.8  
**Branch:** `develop/1.6.0`  
**Scope:** Mailbox connection persistence, encrypted secret vault, Microsoft Settings UI.  
**Out of scope:** Graph, OAuth/tokens, webhooks, delta, mailbox read, detection, LA matching, referral creation, Inbox schema changes.

| | |
| --- | --- |
| **Manual UAT date** | 2026-09-21 |
| **Overall result** | **PASS** |

---

## Environment

| Item | Value |
| --- | --- |
| Staging URL | (staging) |
| Tester | Manual UAT |
| Date | 2026-09-21 |
| wp-config key present? | Yes (staging only — key value not recorded) |

---

## MIGRATION

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | DB upgrades 2.31.0 → 2.32.0 | **PASS** | Additive / idempotent |
| 2 | `jmrs_mailbox_connections` exists | **PASS** | |
| 3 | `jmrs_mailbox_connection_secrets` exists | **PASS** | |
| 4 | Migration re-run safe / idempotent | **PASS** | |
| 5 | Existing referrals / Inbox / LA / settings preserved | **PASS** | No operational tables modified |

---

## SETTINGS ACCESS

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Platform Administrator allowed | **PASS** | `jmrs_manage_settings` |
| 2 | Authorised WP Admin allowed | **PASS** | |
| 3 | Referral Manager denied | **PASS** | |
| 4 | Care Coordinator denied | **PASS** | |
| 5 | Assessor denied | **PASS** | |
| 6 | Support Worker denied | **PASS** | |
| 7 | Direct URL protected | **PASS** | |
| 8 | POST protected (capability + nonce) | **PASS** | |

---

## MISSING KEY

Before staging key is configured:

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Page loads safely | **PASS** | |
| 2 | Encryption status = key missing | **PASS** | Fail-closed; no WP salt fallback |
| 3 | First save with secret rejected safely | **PASS** | |
| 4 | No plaintext secret stored | **PASS** | |
| 5 | No partial active connection created | **PASS** | |

---

## KEY CONFIGURATION

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | After valid staging-only key: encryption status = Ready | **PASS** | Key external to DB/repo; not displayed in UI |

---

## FIRST SAVE

Use non-production fixture values only.

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Tenant GUID validates | **PASS** | |
| 2 | Client GUID validates | **PASS** | |
| 3 | Mailbox validates | **PASS** | |
| 4 | Mailbox type validates | **PASS** | Shared mailbox |
| 5 | Secret accepted | **PASS** | |
| 6 | Connection row created | **PASS** | |
| 7 | Secret row created | **PASS** | |
| 8 | Status = configured | **PASS** | Configured ≠ Microsoft-verified |
| 9 | Status is NOT connected | **PASS** | |
| 10 | is_enabled = 1 | **PASS** | |

---

## SECRET STORAGE

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Plaintext secret NOT in connection table | **PASS** | |
| 2 | Plaintext secret NOT in secret table | **PASS** | |
| 3 | Ciphertext present | **PASS** | XChaCha20-Poly1305 IETF AEAD |
| 4 | Nonce present | **PASS** | Fresh nonce per encryption |
| 5 | key_version present | **PASS** | `key_version = 1` |
| 6 | DB does NOT contain encryption key | **PASS** | |
| 7 | Page refresh does NOT reveal secret | **PASS** | |
| 8 | Password input remains blank | **PASS** | |
| 9 | UI reports secret stored | **PASS** | “Client secret stored securely”; AAD binds connection_id + secret_name + key_version |

---

## BLANK UPDATE

Change mailbox configuration without entering a new secret.

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Update succeeds | **PASS** | Blank = KEEP EXISTING SECRET |
| 2 | Existing secret remains | **PASS** | Not erased |
| 3 | Ciphertext unchanged where practical to verify | **PASS** | |
| 4 | No secret loss | **PASS** | Field blank after refresh |

---

## REPLACE SECRET

Submit a different test secret.

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Ciphertext changes | **PASS** | Fresh nonce |
| 2 | Secret decryptable internally by service | **PASS** | |
| 3 | Old plaintext not retained | **PASS** | |
| 4 | UI does not reveal new secret | **PASS** | Still reports stored securely |

---

## INVALID CONFIG

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Malformed tenant ID rejected | **PASS** | |
| 2 | Malformed client ID rejected | **PASS** | |
| 3 | Invalid mailbox rejected | **PASS** | |
| 4 | Invalid mailbox type rejected | **PASS** | |
| 5 | Array/scalar attacks rejected safely | **PASS** | provider/auth/status not POST-overridable |

---

## STATUS

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Initial saved status = configured | **PASS** | |
| 2 | Never reports Connected in 5C.1 | **PASS** | `connected` reserved for later verification |

---

## DISABLE

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Disable succeeds | **PASS** | `is_enabled = 0` |
| 2 | Status = disabled | **PASS** | |
| 3 | Secret remains encrypted | **PASS** | Credentials not deleted |
| 4 | Inbox unchanged | **PASS** | |

---

## ENABLE

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Enable succeeds | **PASS** | `is_enabled = 1` |
| 2 | Status = configured | **PASS** | Not connected; no Graph call |
| 3 | No Graph call occurs | **PASS** | |

---

## REMOVE

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Requires POST + nonce + confirmation | **PASS** | |
| 2 | Secret row deleted first | **PASS** | Then connection row |
| 3 | Connection row deleted | **PASS** | |
| 4 | Inbox / referrals untouched | **PASS** | |
| 5 | Final state = not configured | **PASS** | |

---

## CRYPTO FAILURE

Where safely testable:

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Invalid/missing key version fails closed | **PASS** | Missing + invalid key |
| 2 | Corrupted ciphertext fails authentication | **PASS** | |
| 3 | No plaintext returned | **PASS** | Safe application-level errors only |
| 4 | No crypto internals leaked | **PASS** | |

---

## REGRESSION

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | Existing Referral Inbox works | **PASS** | |
| 2 | Existing referrals unchanged | **PASS** | |
| 3 | Referral 10 unchanged (if present) | **PASS** | |
| 4 | Local Authority directory unchanged | **PASS** | |
| 5 | Branding/settings unchanged | **PASS** | Terminology/modules unchanged |
| 6 | Product = 1.5.0 | **PASS** | |
| 7 | DB = 2.32.0 | **PASS** | |
| 8 | Rewrite = 1.2.8 | **PASS** | |

---

## BOUNDARIES

| # | Check | Result | Notes |
| --- | --- | --- | --- |
| 1 | No Graph calls | **PASS** | |
| 2 | No OAuth calls | **PASS** | |
| 3 | No access tokens | **PASS** | |
| 4 | No refresh tokens | **PASS** | |
| 5 | No webhooks | **PASS** | |
| 6 | No delta sync | **PASS** | |
| 7 | No mailbox read | **PASS** | |
| 8 | No detection | **PASS** | |
| 9 | No LA matching | **PASS** | |
| 10 | No referral creation | **PASS** | |

---

## Sign-off

| Role | Name | Date | Verdict |
| --- | --- | --- | --- |
| Tester | Manual UAT | 2026-09-21 | **PASS** |
| Approver | | 2026-09-21 | **PASS** |
