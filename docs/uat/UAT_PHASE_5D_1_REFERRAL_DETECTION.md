# UAT — Phase 5D.1 Trusted sender recognition and deterministic detection

**Product:** 1.5.0 (unchanged)  
**Database:** 2.32.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.8 (unchanged — no new route)  
**Baseline checkpoint:** `feced9b0f1f516eb620c7a43814dc428bfac84d5` (Phase 5C.1)  
**Branch:** `feature/5d-referral-detection`

**Manual UAT date:** **2026-09-28**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.32.0** · Rewrite **1.2.8**

**UAT method:** A temporary admin-only staging runner was used for controlled Inbox fixtures, then **completely removed** before checkpoint. No live `jmrs-uat-5d1-detection` route remains.

**Scope:** Advisory deterministic detection on newly created Referral Inbox rows. Recognised-sender matching reuses `LocalAuthoritySenderMatcher`. Guarded writes update detection only while `unclassified`, and set `local_authority_id` only when it is NULL and the matcher returns MATCH.

**Out of scope:** Schema/migration; new portal routes; Accept Referral; referral creation; AI/external classification; Microsoft Graph/OAuth/polling/webhooks; historical backfill; develop/main merge; tag; package; deploy. The Phase 5C.2 stash was not applied.

---

## Case results

| Case | Expected | Result |
| --- | --- | --- |
| A Recognised sender + referral signal | `likely` / `recognised_sender_and_referral_signal` / authority linked / lifecycle `new` | **PASS** |
| B Recognised sender + neutral message | `uncertain` / `recognised_sender_without_referral_signal` / authority linked / lifecycle `new` | **PASS** |
| C Unrecognised sender + two independent signal categories | `likely` / `multiple_independent_referral_signals` / authority NULL / lifecycle `new` | **PASS** |
| D Unrecognised sender + one referral signal | `uncertain` / `single_referral_signal_unrecognised_sender` / authority NULL / lifecycle `new` / matcher `NO_MATCH` | **PASS** |
| E Ambiguous recognised sender | `uncertain` / `ambiguous_recognised_sender` / authority NULL / matcher `AMBIGUOUS` / two UAT candidates / none chosen | **PASS** |
| F Explicit non-referral | `not_referral` / `explicit_non_referral_signal` | **PASS** |
| G Mixed positive and negative signals | `uncertain` / `mixed_referral_and_non_referral_signals` | **PASS** |
| H No deterministic signals | `unclassified` / `no_deterministic_referral_signal` / authority NULL | **PASS** |
| I Invalid sender + referral signal | Inbox create rejects the invalid sender (**NOT APPLICABLE** as a persisted row, by design). In-memory evaluation: `uncertain` / `invalid_sender_with_referral_signal` / `INVALID_SENDER` | **PASS** |
| J Existing authority protection | Stored authority X remains. Matcher suggestion Y is not written. Detection may classify while the row is still `unclassified` | **PASS** |
| K Existing detection protection | Non-unclassified status and reason stay unchanged. Authority is not replaced. `detection_written` = no | **PASS** |
| L New ingestion auto-detection | `CREATED` → `likely` / `recognised_sender_and_referral_signal` / authority linked / lifecycle `new` | **PASS** |
| M Existing replay | `EXISTING`, same Inbox id, detection and authority unchanged, no automatic re-detection | **PASS** |
| N Lifecycle after detection | Remains `new` | **PASS** |
| O Staff Portal | See below | **PASS** |

### Case D note

The temporary runner showed DIFF because its Actual diagnostic string included `sender NO_MATCH` and the runner Expected string omitted that extra diagnostic. Classification, reason, authority, and lifecycle matched the intended result. This was a runner formatting difference, not a detection-engine failure. Permanent detection code was not changed because of it.

### Case I

Row creation: **NOT APPLICABLE** by design. Normal Inbox validation still rejects an invalid sender. Pure detector evaluation: **PASS**.

### Case O — Staff Portal

| Check | Result |
| --- | --- |
| Detection badge shows the stored result | **PASS** |
| Lifecycle remains New | **PASS** |
| Opening or refreshing does not mutate | **PASS** |
| No new Phase 5D.1 action buttons | **PASS** |
| No Accept Referral button | **PASS** |
| No referral created | **PASS** |

Existing Inbox GET remains non-mutating.

---

## Cleanup

| Item | Count |
| --- | --- |
| Inbox fixtures removed | 11 |
| Attachment fixtures removed | 1 |
| Sender-rule fixtures removed | 4 |
| Authority fixtures removed | 2 |
| Remaining 5D.1 Inbox fixtures | 0 |
| Remaining 5D.1 authority fixtures | 0 |

**Result:** **PASS**

---

## Preservation snapshot

| Check | Before | After |
| --- | --- | --- |
| Referral count | 11 | 11 |
| Referral 10 `updated_at` | 2026-08-27 19:47:21 | 2026-08-27 19:47:21 |
| Mailbox connection count | 0 | 0 |
| Other authority count | 2 | 2 |

**Result:** **PASS**

No real referral changed. Referral 10 was unchanged. No real Local Authority or sender rule was removed. Microsoft connection data and organisation/settings data were unchanged.

---

## Boundaries confirmed

| Check | Result |
| --- | --- |
| Automatic detection writes only while `detection_status = unclassified` | **PASS** |
| Non-null `local_authority_id` is not overwritten | **PASS** |
| `AMBIGUOUS` selects no authority | **PASS** |
| `NO_MATCH` / `INVALID_SENDER` do not clear an authority | **PASS** |
| `EXISTING` ingestion does not re-run detection | **PASS** |
| No AI, no external classifier, no Graph/OAuth | **PASS** |
| No schema change, no new portal route, no historical backfill | **PASS** |
| No referral creation, no Accept action, no lifecycle mutation by the detector | **PASS** |
| 5C.2 stash present and not applied | **PASS** |
