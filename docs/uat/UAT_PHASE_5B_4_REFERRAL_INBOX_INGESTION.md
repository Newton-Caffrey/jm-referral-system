# UAT — Phase 5B.4 Referral Inbox Ingestion

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0**)  
**Database:** **2.31.0** (no migration)  
**Portal rewrite:** **1.2.8** (unchanged)  
**Baseline checkpoint:** `6ceb5af839854070f51dd3c5089eac517f0dfbd8` (Phase 5B.3)  
**Branch:** `develop/1.6.0`

**Scope:** Provider-neutral `InboundMessage` / ingestion gateway → `ReferralInboxService` → DB → Staff Portal → Start Review.

**Out of scope:** Schema/migration; Graph/Gmail/OAuth/webhooks/sync/tokens; detection; LA matching; referral creation; attachment files; production intake UI; main merge; tag; package; deploy.

**Manual UAT date:** **2026-09-21**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.31.0** · Rewrite **1.2.8**

**UAT method:** Temporary admin-only staging harness was used for controlled ingestion tests, then **completely removed** before checkpoint (no live `jmrs-uat-5b4-ingestion` route remains).

### Cleanup (post-UAT)

| Item | Result |
| --- | --- |
| Inbox fixtures removed | **PASS** |
| Attachment fixtures removed | **PASS** |
| Remaining Phase 5B.4 fixtures | **0** |

### Invalid-message attachment verification (Section 1)

Blank `provider_message_id` is rejected at `InboundMessage::try_from()` with **VALIDATION_ERROR** before `ReferralInboxIngestionService::ingest()` runs. Attachment metadata writes only occur after a successful Inbox create/EXISTING. Therefore no Inbox row and **no attachment metadata rows** are created for the invalid message. Recorded **PASS**.

---

## INGESTION

| Check | Result | Notes |
| --- | --- | --- |
| Valid normalised message → CREATED | **PASS** | |
| Inbox ID returned | **PASS** | |
| Exactly one Inbox row | **PASS** | |
| Status new | **PASS** | |
| Detection unclassified | **PASS** | |
| Local Authority null | **PASS** | |
| Two attachment metadata rows | **PASS** | |

Successful ingestion did **not** start review, link authority, classify, create referral, or mark accepted.

---

## REPLAY

| Check | Result | Notes |
| --- | --- | --- |
| Exact replay → EXISTING | **PASS** | Canonical identity + dedupe_key |
| Same Inbox ID | **PASS** | |
| No second Inbox row | **PASS** | |
| No duplicate attachment metadata | **PASS** | |

---

## NEW ATTACHMENT RECONCILIATION

| Check | Result | Notes |
| --- | --- | --- |
| Replay with new attachment | **PASS** | |
| Existing attachments not duplicated | **PASS** | |
| Third attachment added | **PASS** | |
| attachment_count policy (EXISTING not rewritten) | **PASS** | Source-declared at create only |

---

## PARTIAL FAILURE

| Check | Result | Notes |
| --- | --- | --- |
| Inbox row preserved | **PASS** | |
| Valid attachment preserved | **PASS** | |
| Invalid attachment reported | **PASS** | |
| PARTIAL result | **PASS** | |
| No fatal | **PASS** | |

---

## INVALID MESSAGE

| Check | Result | Notes |
| --- | --- | --- |
| Blank message ID rejected | **PASS** | |
| VALIDATION_ERROR | **PASS** | At envelope `try_from` |
| No Inbox row created | **PASS** | |
| No attachment rows created | **PASS** | Verified — ingest not called |

---

## PORTAL

| Check | Result | Notes |
| --- | --- | --- |
| Item appears in Referral Inbox | **PASS** | |
| Test Fixture source | **PASS** | |
| New status | **PASS** | |
| Unclassified detection | **PASS** | |
| LA blank | **PASS** | |
| Correct subject | **PASS** | |
| Attachment metadata visible | **PASS** | Two |
| Body preview correct | **PASS** | |
| No downloads | **PASS** | |
| GET does not mutate | **PASS** | |
| Start Review works | **PASS** | POST only |
| Reviewed actor/time recorded | **PASS** | |

---

## BOUNDARIES

| Check | Result | Notes |
| --- | --- | --- |
| Sender matching NOT run | **PASS** | |
| LA null despite Alpha Council sender | **PASS** | |
| Detection NOT run | **PASS** | |
| No referral created | **PASS** | |
| No Accept action | **PASS** | |
| No attachment files | **PASS** | |
| No Graph/Gmail/OAuth | **PASS** | |
| No webhook/sync/tokens | **PASS** | |

---

## PRESERVATION

| Check | Result | Notes |
| --- | --- | --- |
| Existing referrals unchanged | **PASS** | |
| Referral 10 unchanged | **PASS** | |
| LA directory unchanged | **PASS** | |
| Settings unchanged | **PASS** | |
| DB 2.31.0 | **PASS** | |
| Rewrite 1.2.8 | **PASS** | |
| Product 1.5.0 | **PASS** | |

---

## CLEANUP

| Check | Result | Notes |
| --- | --- | --- |
| Inbox fixtures removed | **PASS** | |
| Attachment fixtures removed | **PASS** | |
| Remaining 5B.4 fixtures = 0 | **PASS** | |

---

## Sign-off

| Role | Name | Date | Signature |
| --- | --- | --- | --- |
| Tester | | 2026-09-21 | Manual staging UAT **PASS** |
| Reviewer | | | |
