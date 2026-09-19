# Referral Inbox Data Model (Phase 5B.1)

**Product:** 1.5.0 (unchanged)  
**Database:** **2.31.0** (additive from 2.30.0)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Branch:** `develop/1.6.0`

Provider-neutral **data foundation** for a future Referral Inbox that will receive referral opportunities from Microsoft 365 / Outlook, Gmail, other connectors, and manual/test ingestion.

---

## Scope of this phase

**In scope**

- `{prefix}jmrs_referral_inbox`
- `{prefix}jmrs_referral_inbox_attachments` (metadata only)
- Lifecycle / detection / source / attachment status domain constants
- Additive migration `2.30.0` → `2.31.0`
- Uninstall registration (attachments before inbox)

**Out of scope (deliberate)**

- Mailbox connections, Graph, Gmail, OAuth, webhooks, polling
- Email parsing / detection / Local Authority matching from messages
- Inbox UI, Accept/Ignore, referral creation
- Attachment binary download/storage
- Connector tables, token storage, sync-state tables
- Repositories / Inbox service workflow (Phase **5B.2**)

---

## Purpose

The Inbox stores **operational metadata** about inbound messages that may become referrals. It is not an email archive.

Future phases:

| Phase | Intent |
| --- | --- |
| 5B.2 | Repositories, service, dedupe key generation, status transitions |
| 5B.3 | Admin Inbox UI |
| 5C | Provider connectors |
| 5D | Detection / classification |

---

## Table: `jmrs_referral_inbox`

Physical name: `{wpdb->prefix}jmrs_referral_inbox` via `Tables::referral_inbox_table()`.

### Identity / provider-neutral source

| Column | Type | Notes |
| --- | --- | --- |
| `source_provider` | VARCHAR(50) | e.g. `microsoft_graph`, `gmail`, `manual`, `fixture` |
| `mailbox_identifier` | VARCHAR(255) | Provider mailbox scope (not a Graph-specific name) |
| `provider_message_id` | VARCHAR(255) | Provider-scoped message id |
| `internet_message_id` | VARCHAR(255) NULL | Secondary evidence; **not** globally unique |
| `conversation_identifier` | VARCHAR(255) NULL | Thread/conversation hint |
| `dedupe_key` | CHAR(64) UNIQUE | SHA-256 hex; generated later from provider + mailbox + message id |

No Graph-specific column names (`graph_message_id`, tenant ids, subscription ids). Sync state belongs in Phase 5C.

### Data minimisation

| Column | Limit | Notes |
| --- | --- | --- |
| `subject` | 500 | Normal subjects; not a full-text index |
| `body_preview` | **1000** | Optional plaintext preview only |
| `recipient_summary` | 500 | Minimal To/Cc-style summary — **not** raw headers; **no Bcc by default** |
| `error_message` | 500 | Safe ops text — no tokens, stack traces, raw provider payloads |

**Not stored:** raw MIME, full HTML bodies, tracking content, OAuth/mailbox tokens, arbitrary headers, attachment bytes.

### Lifecycle vs detection

| Field | Domain class | Values |
| --- | --- | --- |
| `status` | `ReferralInboxStatus` | `new`, `needs_review`, `accepted`, `ignored`, `duplicate`, `error` |
| `detection_status` | `ReferralDetectionStatus` | `unclassified`, `likely`, `uncertain`, `not_referral` |

These are **independent**. Example: `status=needs_review` + `detection_status=likely`.

Conceptual lifecycle: `new` → `needs_review` → `accepted` | `ignored` | `duplicate` | `error`.

`potential_referral` is **not** used as a lifecycle status (detection covers classification).

### Linkage (nullable; no backfill in 5B.1)

- `local_authority_id` — future sender matching; null until later processing
- `linked_referral_id` — future Accept → referral link; no `ReferralService::create()` here
- `duplicate_of_inbox_id` — points at another inbox row when marked duplicate

### Timing / actors (metrics later)

`received_at`, `reviewed_at` / `reviewed_by`, `accepted_at` / `accepted_by`, `ignored_at` / `ignored_by`, `response_started_at`, `response_sent_at`.

### Indexes (rationale)

| Index | Why |
| --- | --- |
| UNIQUE `dedupe_key` | Idempotency under provider redelivery |
| `status`, `detection_status`, `received_at` | List/filter |
| `(status, received_at)`, `(detection_status, received_at)` | Typical Inbox queues |
| `sender_email`, `sender_domain` | Operational lookup |
| `local_authority_id`, `linked_referral_id` | Future joins |
| `internet_message_id` | Secondary duplicate evidence (non-unique) |
| `source_provider` | Connector filtering |

Referential integrity for LA / referral ids is **application-layer** (consistent with JMRS — no new DB FKs).

---

## Table: `jmrs_referral_inbox_attachments`

Physical name: `{wpdb->prefix}jmrs_referral_inbox_attachments` via `Tables::referral_inbox_attachments_table()`.

Metadata only. Default `storage_status` = `metadata_only`.

| Column | Notes |
| --- | --- |
| `inbox_id` | Parent inbox item (app-enforced) |
| `provider_attachment_id` | Provider-scoped attachment id |
| `filename`, `mime_type`, `size_bytes` | Optional metadata |
| `sha256` | Nullable until bytes exist |
| `storage_status` | `InboxAttachmentStatus` |
| `private_path` | Reserved for future private storage — unused in 5B.1 |

**Statuses:** `metadata_only`, `quarantined`, `stored`, `promoted`, `deleted`, `error`.

**Indexes:** `inbox_id`, `sha256`, `storage_status`.

No public Media Library; no file writes in this phase.

---

## Domain classes

Namespace `JMReferral\ReferralInbox`:

- `ReferralInboxStatus`
- `ReferralDetectionStatus`
- `ReferralInboxSource`
- `InboxAttachmentStatus`
- `ReferralInboxLimits`

No repositories or workflow services yet (5B.2).

---

## Uninstall / retention

Opt-in wipe registers attachments **before** inbox. Default uninstall remains non-destructive (roles/caps only). No retention cron in 5B.1.

---

## Security / privacy

Inbound email content is untrusted. Schema stores previews as plain text bounds only — never trusted HTML, remote images, or tracking pixels. Error fields must not leak credentials or provider secrets.
