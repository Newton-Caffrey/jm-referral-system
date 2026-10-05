# Referral Inbox Detection (Phase 5D.1)

**Product:** 1.5.0 (unchanged)  
**Database:** **2.33.0** (Phase 5D.2 adds Local Authority provenance columns)
**Portal rewrite:** **1.2.8** (unchanged — no new route)  
**Branch:** `feature/5d-referral-detection`

Advisory, deterministic classification of Referral Inbox metadata.

This is not AI, not an external classifier, and not referral conversion. Detection does not change Inbox lifecycle status and does not call `ReferralService::create()`.

Recognised sender means a configured Local Authority sender rule matched. It does not mean the message is cryptographically authenticated.

---

## Components

| Class | Role |
| --- | --- |
| `ReferralInboxDetectionRules` | Fixed positive phrases, filename tokens, and explicit negative phrases |
| `ReferralInboxDetectionService` | `evaluate()`, `evaluateFields()`, `evaluateAndApply()` |
| `ReferralInboxDetectionResult` | Advisory result. Only status + reason are persistence candidates |
| `ReferralInboxService::applyGuardedDetection()` | Compare-and-set writer |
| `ReferralInboxRepository::update_detection_if_unclassified()` | `WHERE detection_status = 'unclassified'` |
| `ReferralInboxRepository::set_local_authority_if_null()` | `WHERE local_authority_id IS NULL AND local_authority_origin IS NULL` |

`LocalAuthoritySenderMatcher` is reused. Sender-rule precedence and domain label boundaries are unchanged.

`ReferralInboxIngestionService` calls `evaluateAndApply()` only when **this** ingest call inserted a new Inbox row (`ReferralInboxService::create()` returned `CREATED`). That includes a `PARTIAL` ingestion result when the row itself was new. `EXISTING` replays do not re-detect.

There is no historical backfill on plugin load, migration, activation, or admin requests.

---

## What is evaluated

Already-stored bounded metadata only:

- subject
- body preview
- attachment filenames
- sender match from `LocalAuthoritySenderMatcher`

Not used: raw MIME, raw HTML, remote URLs, attachment bytes, external APIs.

Stored message text is not modified. Matching normalises a copy: lowercase, separators `-` `_` `.` become boundaries, then a padded literal phrase search. Patterns are class constants, not caller input.

---

## Signal sources

Three independent categories:

1. `subject`
2. `body_preview`
3. attachment filenames (every filename is one category, not one per file)

Several phrases in the same subject still count as one source. A subject stuffed with keywords cannot by itself satisfy the two-source likely rule.

Filename tokens (`referral`, `referral-form`, `care-plan`, `support-plan`, `needs-assessment`) apply to filenames only. The bare word `referral` is not a subject or body signal. The words `care`, `support`, and `assessment` alone are not signals.

---

## Positive phrases

`new referral`, `referral form`, `referral opportunity`, `supported living referral`, `home care referral`, `domiciliary care referral`, `residential care referral`, `placement request`, `placement opportunity`, `care package`, `care package request`, `request for care`, `request for support`, `support package`, `care enquiry`.

---

## Negative phrases

`automatic reply`, `out of office`, `undeliverable`, `delivery status notification`, `invoice`, `remittance`, `payment confirmation`, `newsletter`, `password reset`.

These are explicit non-referral signals. A message with no positive phrase stays `unclassified` unless another rule applies (for example a recognised sender with no signal). Negative phrases are scanned on subject, body preview, and filenames.

---

## Precedence

First match wins:

| Order | Condition | Status | Reason code |
| --- | --- | --- | --- |
| 1 | Any positive signal and any explicit negative signal | `uncertain` | `mixed_referral_and_non_referral_signals` |
| 2 | Explicit negative signal and no positive signal | `not_referral` | `explicit_non_referral_signal` |
| 3 | Matcher `AMBIGUOUS` | `uncertain` | `ambiguous_recognised_sender` |
| 4 | Matcher `INVALID_SENDER` and at least one positive signal | `uncertain` | `invalid_sender_with_referral_signal` |
| 5 | Matcher `MATCH` and at least one positive signal | `likely` | `recognised_sender_and_referral_signal` |
| 6 | Matcher `MATCH` and no positive signal | `uncertain` | `recognised_sender_without_referral_signal` |
| 7 | Unrecognised sender and positive signals in two or more source categories | `likely` | `multiple_independent_referral_signals` |
| 8 | Unrecognised sender and a positive signal in exactly one source category | `uncertain` | `single_referral_signal_unrecognised_sender` |
| 9 | No deterministic signal | `unclassified` | `no_deterministic_referral_signal` |

Rule 2 can classify a recognised sender’s invoice as `not_referral`. Rule 3 does not pick an authority. Rule 4 does not treat a malformed sender as `likely`, even with two positive sources.

---

## Authority suggestion

Independent of detection status.

| Matcher | Automatic authority write |
| --- | --- |
| `MATCH` | Set `local_authority_id` and `local_authority_origin = suggested` only when **both** `local_authority_id` and `local_authority_origin` are `NULL`. `decided_by` and `decided_at` stay `NULL`. |
| `AMBIGUOUS` | Never. Candidates stay on the result object and are not stored |
| `NO_MATCH` | Never. Does not clear an existing id or a `cleared` origin |
| `INVALID_SENDER` | Never. Does not clear an existing id or a `cleared` origin |

Examples:

- Recognised sender + invoice → `not_referral`, and the authority may still be stored if the column was null.
- Recognised sender + neutral message → `uncertain`, and the authority may still be stored if the column was null.
- Ambiguous sender + referral subject → `uncertain`, authority stays null.

A non-null authority is never replaced by a later automatic `MATCH` for a different authority. A recorded origin of `confirmed` or `cleared` also blocks the automatic write, including when `cleared` has set the authority id back to `NULL`. Historical rows with a non-null id and a `NULL` origin are left unchanged and are not backfilled. Staff explanation and human confirm/clear are documented in [`REFERRAL_INBOX_AUTHORITY_REVIEW.md`](REFERRAL_INBOX_AUTHORITY_REVIEW.md).

---

## Persistence guards

`evaluate($inboxId)` and `evaluateFields()` do not write.

`evaluateAndApply($inboxId)`:

1. Evaluates.
2. Writes detection status and reason only while `detection_status = unclassified`.
3. Writes the suggested authority only on `MATCH` when `local_authority_id IS NULL` and `local_authority_origin IS NULL`, and records origin `suggested`.
4. Returns `applied`, `unchanged`, or `not_found`.

There is no force/overwrite flag.

`setDetectionMetadata()` still writes status, reason, and authority together. Automatic detection does not call it. Passing null to that older method can clear an authority.

If detection throws after a valid Inbox insert, ingestion still returns the create/partial result, leaves lifecycle `new`, and leaves detection `unclassified` when the write did not happen. The exception is not logged with message content.

---

## Reason codes

Persisted `detection_reason` is one of the codes in the precedence table. It does not contain subject text, body excerpts, filenames, sender text, or other extracted personal data.

Evidence codes and ambiguous candidate ids/names exist only on `ReferralInboxDetectionResult` for a later explanation screen (Phase 5D.2). They are not columns.

---

## Out of scope for 5D.1

- Staff Portal changes, new routes, Accept Referral, prefilled referral UI
- Referral creation
- Lifecycle transitions (`markNeedsReview`, `markIgnored`, `markDuplicate`, `markAccepted`, `markError`)
- Schema, provenance column, detection timestamp, scores, extracted-field columns
- Microsoft Graph, OAuth, polling, webhooks, delta sync
- Re-detection of historical rows
- Display of `detection_reason` (the existing detection badge already shows the stored status)
