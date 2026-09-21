# Microsoft 365 Connection Foundation (Phase 5C.1)

Persistent Microsoft 365 mailbox connection configuration for JMRS **before** any live Microsoft Graph request.

| | |
| --- | --- |
| **Phase** | 5C.1 |
| **Product** | 1.5.0 (unchanged) |
| **Database** | **2.32.0** |
| **Portal rewrite** | 1.2.8 (unchanged) |
| **Auth mode** | Application / client-credentials |
| **Live Graph / OAuth** | **Not implemented** |

Related: [`SECRET_STORAGE.md`](SECRET_STORAGE.md), [`DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md), [`SERVICES.md`](SERVICES.md).

---

## Commercial model (first release)

**One JMRS installation = one care provider = one customer-owned Entra application.**

Each care provider creates a **single-tenant** Microsoft Entra (Azure AD) application in **their** Microsoft 365 tenant.

JMRS stores:

- Tenant ID
- Application (client) ID
- Encrypted client secret (vault)
- Mailbox address + type (`user` | `shared`)

JMRS does **not** distribute a shared vendor client secret across WordPress installations.

Future multi-tenant / vendor-app onboarding is possible but **out of scope** for 5C.1.

---

## Architecture position

Intended future flow (not built in 5C.1):

```text
Microsoft Graph
  → Microsoft connector
  → InboundMessage
  → ReferralInboxIngestionService
  → Referral Inbox
```

5C.1 provides only:

- `jmrs_mailbox_connections` (configuration, no secrets)
- `jmrs_mailbox_connection_secrets` (ciphertext only)
- `MicrosoftConnectionService` + admin Settings UI
- Dedicated encryption key via wp-config (see [`SECRET_STORAGE.md`](SECRET_STORAGE.md))

---

## Product constraint: one active Microsoft mailbox

**Application layer** enforces: at most **one enabled** `microsoft_graph` connection per installation.

This is a **product** constraint for the first release, **not** a permanent database limitation. Schema has **no** `UNIQUE(provider)` so multi-mailbox can be added later without a destructive redesign.

---

## Connection status model

Persisted statuses:

| Status | Meaning in 5C.1 |
| --- | --- |
| `configured` | Complete config + secret stored; **not** Microsoft-verified |
| `connected` | Reserved for 5C.2+ after real verification |
| `attention_required` | Reserved |
| `reauthorization_required` | Reserved |
| `disabled` | Explicitly disabled (`is_enabled = 0`) |
| `error` | Reserved |

Computed when no row exists: `not_configured`.

After a successful first save in 5C.1, status is **`configured`**, never **`connected`**.

Enable after disable restores **`configured`** (still no Graph call).

---

## Admin Settings location

```text
J&M Referrals → Settings → Integrations → Microsoft 365
```

Also available as submenu: **J&M Referrals → Microsoft 365**.

Capability: `jmrs_manage_settings` (Platform Administrator / WP Administrator per existing policy).

**Not** exposed in Staff Portal. Referral Manager / Care Coordinator / Assessor / Support Worker are denied by default.

---

## Fields (Microsoft application auth)

| Field | Rules |
| --- | --- |
| Tenant ID | GUID |
| Client ID | GUID |
| Mailbox address | Valid email |
| Mailbox type | `user` \| `shared` |
| Client secret | Required on create; blank on update = keep existing |

Internally fixed (POST cannot override): `provider=microsoft_graph`, `auth_mode=application`, `credential_type=client_secret` (initial).

---

## Exchange Online RBAC (onboarding expectation)

Production onboarding is expected to use **Exchange Online RBAC for Applications** to scope Application `Mail.Read` to the configured referral mailbox / resource scope.

JMRS does **not** execute Exchange PowerShell and does **not** configure tenant RBAC automatically.

**Warning:** An additional **unscoped** Entra `Mail.Read` application grant can make effective access broader because Entra and Exchange application grants are **additive**. Prefer scoped Exchange RBAC and avoid broad Mail.Read where possible.

---

## Credential guidance

Microsoft recommends **certificate** credentials over client secrets for higher-assurance confidential-client production deployments.

5C.1 implements **encrypted client-secret** storage as the first integration path. The secret schema/service remains generic (`secret_name`, algorithm, key version) so a future **certificate** credential can be added without redesigning the vault tables.

Do not claim client secrets are Microsoft’s preferred production credential.

---

## Explicit non-goals (5C.1)

- No Microsoft Graph HTTP calls
- No OAuth / token endpoint calls
- No access_token / refresh_token storage
- No webhooks / Graph subscriptions
- No delta sync / mailbox read / attachment retrieval
- No cron, detection, LA matching, referral creation
- No Inbox schema changes
- No Staff Portal credential UI

---

## Classes

| Class | Role |
| --- | --- |
| `MailboxConnectionRepository` | Connection CRUD |
| `MailboxConnectionSecretRepository` | Ciphertext rows |
| `MailboxConnectionSecretService` | Encrypt/store/has/retrieve/delete |
| `MicrosoftConnectionService` | Validate, one-active rule, save/disable/enable/remove, safe view |
| `Microsoft365SettingsPage` | wp-admin UI |
| `MailboxConnectionStatus` / `MailboxConnectionConstants` | Status + allowlists |

---

## Uninstall

Default uninstall remains **non-destructive**.

Opt-in wipe (`JMRS_DELETE_DATA_ON_UNINSTALL` strictly `true`) drops `jmrs_mailbox_connection_secrets` **before** `jmrs_mailbox_connections`.
