# Referral Inbox Service (Phase 5B.2)

**Product:** 1.5.0  
**Database:** **2.31.0** (unchanged — no migration)  
**Portal rewrite:** 1.2.7  
**Branch:** `develop/1.6.0`

Provider-neutral **domain/service engine** on top of the Phase 5B.1 schema.

**Still out of scope:** Inbox UI, Graph/Gmail/OAuth/webhooks/sync/tokens, detection/classification, `ReferralService::create()`, attachment file I/O, cron.

---

## Components

| Class | Role |
| --- | --- |
| `ReferralInboxIdentity` | Validate identity inputs; deterministic SHA-256 `dedupe_key` |
| `ReferralInboxTransitions` | Allowed lifecycle graph |
| `ReferralInboxResult` | Structured result codes |
| `ReferralInboxRepository` | Prepared SQL; compare-and-set transitions |
| `ReferralInboxAttachmentRepository` | Attachment metadata CRUD |
| `ReferralInboxService` | Application boundary for all Inbox writes |

Wired for DI readiness in `Plugin` — **no admin menu/controllers**.

---

## Identity / dedupe

Canonical identity:

1. `source_provider` (allowlisted, lowercased)
2. `mailbox_identifier` (trim only — opaque, case preserved)
3. `provider_message_id` (trim only — opaque, case preserved)

Canonical string (length-prefixed to avoid delimiter collision):

```text
v1
{len}:{source_provider}
{len}:{mailbox_identifier}
{len}:{provider_message_id}
```

`dedupe_key` = lowercase hex SHA-256 of that string.

Callers **must not** supply a trusted `dedupe_key` — the service ignores/rejects external keys and always regenerates.

---

## Create / idempotency

1. Validate + generate `dedupe_key`
2. Lookup existing by key → `EXISTING`
3. Insert
4. On UNIQUE race → re-fetch → `EXISTING` (`reason=dedupe_key_race`)

DB UNIQUE on `dedupe_key` is the final safety net.

Defaults: `status=new`, `detection_status=unclassified`, nullable links/actors/errors, `attachment_count` from create input (default 0).

---

## State machine

```text
new → needs_review | duplicate | error
needs_review → accepted | ignored | duplicate | error
error → needs_review
accepted | ignored | duplicate → (terminal)
```

Forbidden examples: `new → accepted`, `ignored → needs_review`, `accepted → needs_review`.

Transitions use **compare-and-set**:

```sql
UPDATE ... SET status = :new WHERE id = :id AND status = :expected
```

Results: `SUCCESS`, `ALREADY_APPLIED`, `INVALID_TRANSITION`, `NOT_FOUND`, `CONFLICT`, `VALIDATION_ERROR`, `PERSISTENCE_ERROR`, plus create `CREATED` / `EXISTING`.

### Operations

| Method | Notes |
| --- | --- |
| `markNeedsReview` | From `new` or `error`. Recovery from `error` **clears** `error_code` / `error_message`. |
| `markReviewed` | Requires `needs_review`; **record-once** first `reviewed_at` / `reviewed_by`. |
| `markIgnored` | From `needs_review` only; sets ignore actor/time. |
| `markDuplicate` | From `new` or `needs_review`; validates target exists and ≠ self. |
| `markError` | From `new` or `needs_review`; bounded safe error fields. |
| `markAccepted` | From `needs_review` **only** with existing `referral_id`; does **not** create referrals. |

---

## Detection / Local Authority

`setDetectionMetadata()` records `detection_status` / `detection_reason` / optional `local_authority_id` for future Phase 5D.

- Does **not** call `LocalAuthoritySenderMatcher`
- Does **not** change lifecycle status
- Non-null LA ID must exist

---

## Attachments

`addAttachmentMetadata()`:

- Inbox must exist
- Phase 5B.2 creates **`metadata_only` only**
- `sha256` null or 64-char lowercase hex
- Best-effort idempotency on `inbox_id` + `provider_attachment_id` when ID present
- **Limitation:** no DB UNIQUE on that pair — not race-proof under concurrent inserts
- Does **not** rewrite `attachment_count`

**`attachment_count` semantics:** source-declared count from create input — not metadata-row count.

---

## Privacy

Untrusted email-derived input: tags stripped, lengths bounded, no HTML trust, no tokens/MIME archive, prepared SQL, no DB error strings returned to callers.

No writes to `jmrs_referral_activity`.
