# UAT — Phase 5B.1 Referral Inbox Data Foundation

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0**)  
**Database:** **2.31.0** (from **2.30.0**)  
**Portal rewrite:** 1.2.7  
**Baseline checkpoint:** `2958c349ecc039686e91e401d12ecd5138227d9c` (Phase 5A.3)  
**Branch:** `develop/1.6.0`

**Scope:** Inbox + attachment metadata tables, domain status/source constants, additive migration, docs. No UI.

**Out of scope:** Graph/Gmail/OAuth/webhooks/mailbox sync/tokens; detection; referral creation; attachment download; Inbox UI; repositories/workflow (5B.2); main merge; tag; package; deploy.

**Manual UAT date:** **2026-09-19**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.31.0** · Rewrite **1.2.7**

---

## MIGRATION

| Check | Result | Notes |
| --- | --- | --- |
| Plugin loads with no fatal | **PASS** | |
| DB upgrades 2.30.0 → 2.31.0 | **PASS** | |
| Inbox table exists | **PASS** | `jmrs_referral_inbox` |
| Attachment metadata table exists | **PASS** | `jmrs_referral_inbox_attachments` |
| Required Inbox columns exist | **PASS** | |
| Required attachment columns exist | **PASS** | |
| Required indexes exist | **PASS** | |
| `dedupe_key` UNIQUE exists | **PASS** | |

---

## PRESERVATION

| Check | Result | Notes |
| --- | --- | --- |
| Existing referrals preserved | **PASS** | |
| Referral 10 unchanged | **PASS** | |
| Service catalogue preserved | **PASS** | |
| Local Authority directory preserved | **PASS** | |
| Sender rules preserved | **PASS** | |
| Phase 5A organisation settings preserved | **PASS** | |
| Phase 5A terminology/modules preserved | **PASS** | |
| Dashboard unchanged | **PASS** | |
| Workflow unchanged | **PASS** | |

---

## IDEMPOTENCY STRUCTURE

| Check | Result | Notes |
| --- | --- | --- |
| Valid unique `dedupe_key` accepted | **PASS** | Fixture A (`fixture` / `uat-mailbox` / `uat-message-001`) inserted |
| Duplicate `dedupe_key` rejected by DB constraint | **PASS** | Fixture B (different `provider_message_id`, same `dedupe_key`) → UNIQUE violation; Fixture B never persisted |
| Same `internet_message_id` may exist on different Inbox rows | **PASS** | Fixture C inserted with different `dedupe_key` |
| Nullable `linked_referral_id` accepted | **PASS** | Fixture D |
| Nullable `local_authority_id` accepted | **PASS** | Fixture D |

DB UNIQUE on `dedupe_key` confirmed as the final idempotency safety net.

---

## STATUS / DOMAIN DEFINITIONS

Lifecycle (`ReferralInboxStatus`): `new`, `needs_review`, `accepted`, `ignored`, `duplicate`, `error` — **confirmed**.

Detection (`ReferralDetectionStatus`): `unclassified`, `likely`, `uncertain`, `not_referral` — **confirmed**.

Lifecycle and detection are **separate**. Status strings are centralised in domain helpers. Transition rules and detection logic are **not** implemented (5B.2 / 5D). Exhaustive status value exercise not required in 5B.1.

---

## ATTACHMENTS

| Check | Result | Notes |
| --- | --- | --- |
| Metadata row can reference valid Inbox ID | **PASS** | Fixture A attachment 1 (`uat-attachment-001`, `referral-test.pdf`, `metadata_only`, `sha256` NULL) |
| Multiple attachment metadata rows per Inbox item | **PASS** | Attachment 2 (`uat-attachment-002`, `care-plan-test.docx`) |
| `sha256` may be null for `metadata_only` | **PASS** | Both rows |
| No public file created | **PASS** | No Media Library write; no download |

---

## FIXTURE CLEANUP

| Check | Result | Notes |
| --- | --- | --- |
| Temporary Inbox fixtures removed | **PASS** | |
| Temporary attachment metadata fixtures removed | **PASS** | |
| No real referral data removed | **PASS** | |
| Local Authority staging fixtures untouched | **PASS** | Alpha/Beta Council left as-is |
| No 5B.1 Inbox/attachment UAT fixtures remain | **PASS** | Fixture B never existed (DB rejected) |

---

## DATA MINIMISATION / DESIGN CONFIRMATIONS

| Topic | Result |
| --- | --- |
| `body_preview` ≤1000 plaintext only | **PASS** (documented) |
| `recipient_summary` ≤500 minimal metadata | **PASS** (documented) |
| No MIME/HTML archive, tokens, passwords, Bcc archive, tracking content | **PASS** |
| Provider-neutral sources (`microsoft_graph`, `gmail`, `manual`, `fixture`) | **PASS** |
| No Graph/Gmail-specific schema columns | **PASS** |
| Nullable `local_authority_id` / `linked_referral_id` / `duplicate_of_inbox_id`; no auto-link | **PASS** |
| Timing + actor fields present; no metrics yet | **PASS** |
| Attachment statuses defined; no file I/O in 5B.1 | **PASS** |
| Additive only — no referral/service/LA/activity schema changes | **PASS** |
| Uninstall: attachments before inbox; default non-destructive; no cron | **PASS** |

---

## BOUNDARIES

| Check | Result | Notes |
| --- | --- | --- |
| No Inbox UI | **PASS** | |
| No Graph | **PASS** | |
| No Gmail | **PASS** | |
| No OAuth | **PASS** | |
| No webhook | **PASS** | |
| No mailbox sync | **PASS** | |
| No email tokens | **PASS** | |
| No referral creation | **PASS** | |
| No Inbox repository/service workflow | **PASS** | Deferred to 5B.2 |
| No detection / sender matching | **PASS** | |
| No attachment download / cron | **PASS** | |

---

## Sign-off

| Role | Name | Date | Signature |
| --- | --- | --- | --- |
| Tester | | 2026-09-19 | Focused manual UAT |
| JM Project Owner | | | |

**Verdict after UAT:** **PHASE 5B.1 PASS**
