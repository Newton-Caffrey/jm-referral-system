# Referral Inbox Ingestion Gateway (Phase 5B.4)

**Product:** 1.5.0  
**Database:** **2.31.0** (unchanged — no migration)  
**Portal rewrite:** **1.2.8** (unchanged)  
**Branch:** `develop/1.6.0`

Provider-neutral **ingestion boundary** for future mailbox connectors.

**Still out of scope:** Microsoft Graph / Outlook / Gmail / OAuth / webhooks / polling / sync cursors / tokens; referral creation; attachment file download/storage; production manual-intake UI.

Phase **5D.1** adds advisory detection after a **newly inserted** Inbox row. See [`REFERRAL_INBOX_DETECTION.md`](REFERRAL_INBOX_DETECTION.md). Phase **5D.2** records suggestion provenance. DB **2.33.0**. Rewrite remains **1.2.8**.

---

## Components

| Class | Role |
| --- | --- |
| `InboundMessage` | Immutable-style envelope DTO (`try_from`) |
| `InboundAttachmentMetadata` | Attachment metadata DTO (no binary / path / storage_status from connector) |
| `ReferralInboxIngestionService` | Orchestrates `ReferralInboxService::create` + `addAttachmentMetadata` |
| `ReferralInboxIngestionResult` | `CREATED` / `EXISTING` / `PARTIAL` / `VALIDATION_ERROR` / `PERSISTENCE_ERROR` |

Connectors **must not** call repositories or `ReferralInboxIdentity` directly.

---

## Ingest flow

```text
InboundMessage
  → ReferralInboxIngestionService::ingest()
    → ReferralInboxService::create()   // identity + dedupe + row
    → ReferralInboxService::addAttachmentMetadata() per attachment
    → ReferralInboxDetectionService::evaluateAndApply() only if create() returned CREATED
  → structured result
```

Successful ingestion leaves lifecycle `status` = `new`.

Phase 5D.1 may then set `detection_status`, `detection_reason`, and (only on a unique MATCH while both `local_authority_id` and `local_authority_origin` are NULL) a suggested Local Authority. `EXISTING` replays do not run detection again. A detection failure does not delete the row, does not set lifecycle `error`, and does not fail ingestion.

No `markNeedsReview`, no `ReferralService::create()`.

---

## Idempotency

Canonical identity (unchanged from 5B.2):

`source_provider` + `mailbox_identifier` + `provider_message_id` → `dedupe_key`

| Call | Inbox | Attachments |
| --- | --- | --- |
| First | `CREATED` | metadata rows created (`metadata_only`) |
| Exact replay | `EXISTING` | same `provider_attachment_id` → `EXISTING` (no sequential duplicates) |
| Replay with new attachment | `EXISTING` | missing IDs added; existing not duplicated |

DB UNIQUE on `dedupe_key` remains the final safety net.

---

## Attachment-count semantics

`attachment_count` on the Inbox row = **source-declared** count  
(`InboundMessage::declared_attachment_count()` → create input).

It is **not** redefined as the number of metadata rows currently stored.

**EXISTING rows:** ingestion does **not** rewrite `attachment_count` when a replay declares a different count. New metadata rows may still be reconciled onto the existing item. Documented and intentional — no schema change.

---

## Partial attachment failure

If the Inbox item is `CREATED` / `EXISTING` but one or more attachments fail validation/persistence:

- Inbox row is **kept**
- Valid attachments are kept
- Result = `PARTIAL` with safe per-attachment errors
- Retry = replay the same message identity (idempotent for successes)

If message create fails (`VALIDATION_ERROR` / `PERSISTENCE_ERROR`):

- No attachment writes are attempted
- No orphan attachment metadata

---

## Security

All inbound content is untrusted. Bounding/sanitisation remains in `ReferralInboxService`.

Gateway rejects connector attempts to supply: tokens, raw MIME/HTML, headers, Graph/Gmail objects, `sender_domain`, `storage_status`, `private_path`, binary content fields.

SHA-256 format is enforced by `ReferralInboxService` so a bad hash can surface as attachment-level failure → `PARTIAL`.

Never log bodies, tokens, raw messages, or attachment bytes.

---

## Source providers

Allowlisted via existing constants: `microsoft_graph`, `gmail`, `manual`, `fixture`.

No vendor-specific logic in this gateway.

---

## Related

- [`REFERRAL_INBOX_SERVICE.md`](REFERRAL_INBOX_SERVICE.md)
- [`REFERRAL_INBOX_UI.md`](REFERRAL_INBOX_UI.md)
- [`../uat/UAT_PHASE_5B_4_REFERRAL_INBOX_INGESTION.md`](../uat/UAT_PHASE_5B_4_REFERRAL_INBOX_INGESTION.md)
