# UAT — Phase 5D.2 Detection explanation and Local Authority confirmation

**Product:** 1.5.0  
**Database:** 2.33.0  
**Portal rewrite:** 1.2.8  
**Previous checkpoint:** `0ef5e6f54c22c1e465fd9d01edbfee2e5ce96909` (Phase 5D.1)  
**Branch:** `feature/5d-referral-detection`

**Manual UAT date:** **2026-09-28**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.33.0** · Rewrite **1.2.8**

**UAT method:** A temporary admin-only staging runner prepared scoped fixtures and service checks. Staff then confirmed the portal behaviour. The runner was completely removed before this checkpoint. No live UAT page remains.

**Scope:** Staff explanation of stored detection, a read-only current sender-recognition evaluation, and human confirm/clear of a Local Authority while the Inbox item is `needs_review`.

**Out of scope:** Accept Referral; referral creation; AI/external classification; Microsoft Graph/OAuth/polling/webhooks; a production detection re-run action; a new portal route; historical provenance backfill; develop/main merge; tag; package; deploy. The Phase 5C.2 stash was not applied.

One additive migration only: **2.32.0 → 2.33.0**.

---

## Case A — Automatic suggestion

| Check | Result |
| --- | --- |
| Recognised sender fixture created | **PASS** |
| Local Authority automatically linked | **PASS** |
| Origin = `suggested` | **PASS** |
| `decided_by` = NULL | **PASS** |
| `decided_at` = NULL | **PASS** |
| Lifecycle remained New | **PASS** |

## Case B — New item detail

| Check | Result |
| --- | --- |
| Detection explanation visible | **PASS** |
| Sender recognition visible | **PASS** |
| Recognised-sender disclaimer visible | **PASS** |
| Suggested Local Authority visible | **PASS** |
| “Suggested by JMRS” visible | **PASS** |
| Confirm control hidden while New | **PASS** |
| Clear control hidden while New | **PASS** |
| Start Review instruction visible | **PASS** |
| Page refresh caused no mutation | **PASS** |

## Case C — Confirm suggested authority

| Check | Result |
| --- | --- |
| Start Review succeeded | **PASS** |
| Existing suggested authority confirmed | **PASS** |
| Authority ID unchanged | **PASS** |
| Origin → `confirmed` | **PASS** |
| `decided_by` = current user | **PASS** |
| `decided_at` populated | **PASS** |
| Detection status unchanged | **PASS** |
| Detection reason unchanged | **PASS** |

## Case D — Change authority

| Check | Result |
| --- | --- |
| Alternative active authority selectable | **PASS** |
| Authority changed to the selected id | **PASS** |
| Origin = `confirmed` | **PASS** |
| Actor recorded | **PASS** |
| Time recorded | **PASS** |
| Detection unchanged | **PASS** |

## Case E — Clear authority

| Check | Result |
| --- | --- |
| Clear succeeded | **PASS** |
| `local_authority_id` = NULL | **PASS** |
| Origin = `cleared` | **PASS** |
| Actor recorded | **PASS** |
| Decision time recorded | **PASS** |

## Case F — Cleared blocks auto re-suggestion

| Check | Result |
| --- | --- |
| Matcher still recognised the sender | **PASS** |
| Authority remained NULL | **PASS** |
| Origin remained `cleared` | **PASS** |
| Automatic detector did not re-add the authority | **PASS** |

Automatic authority write requires `local_authority_id IS NULL` and `local_authority_origin IS NULL`.

## Case G — Confirmed blocks auto overwrite

| Check | Result |
| --- | --- |
| Authority X confirmed manually | **PASS** |
| Detector later suggested Y | **PASS** |
| Stored X remained unchanged | **PASS** |
| Origin remained `confirmed` | **PASS** |
| Original actor and time remained unchanged | **PASS** |

## Case H — Ambiguous sender

| Check | Result |
| --- | --- |
| Detection = Uncertain | **PASS** |
| Ambiguous sender message visible | **PASS** |
| Candidate authority names visible | **PASS** |
| No authority automatically selected | **PASS** |
| Staff could Start Review | **PASS** |
| Staff could manually select an active authority | **PASS** |
| Manual confirmation succeeded | **PASS** |

## Case I — Matcher versus stored authority

| Check | Result |
| --- | --- |
| Current sender recognition displayed separately | **PASS** |
| Stored Local Authority displayed separately | **PASS** |
| GET did not alter the stored authority | **PASS** |

Current sender recognition and the stored authority decision remain separate facts.

## Case J — Cleared display

| Check | Result |
| --- | --- |
| “Cleared by staff” visible | **PASS** |
| Decision actor visible | **PASS** |
| Decision time visible | **PASS** |
| Current sender may still display Recognised | **PASS** |
| Recognition and the human decision shown separately | **PASS** |

## Case K — Legacy null origin

| Check | Result |
| --- | --- |
| Stored authority with origin NULL displayed safely | **PASS** |
| Wording “Linked authority — source not recorded” | **PASS** |
| Automatic detector did not overwrite it | **PASS** |

Legacy provenance was not backfilled.

## Case L — Terminal state

| Check | Result |
| --- | --- |
| Authority information visible | **PASS** |
| Confirm control absent | **PASS** |
| Clear control absent | **PASS** |
| No reopen action | **PASS** |

## Case M — Stale action

| Check | Result |
| --- | --- |
| Item opened in two tabs | **PASS** |
| Tab A ignored the item | **PASS** |
| Stale confirm from Tab B rejected | **PASS** |
| Friendly conflict message | **PASS** |
| Terminal lifecycle preserved | **PASS** |
| Authority decision not written | **PASS** |
| No SQL or error dump | **PASS** |

## Access and security

| Check | Result |
| --- | --- |
| Platform Administrator manages authority | **PASS** |
| Referral Manager manages authority | **PASS** |
| Care Coordinator manages authority | **PASS** |
| Assessor denied | **PASS** |
| Support Worker denied | **PASS** |
| Nonce failure denied | **PASS** |
| Unauthorised POST denied | **PASS** |
| Forged actor ignored | **PASS** |
| Array authority ID rejected safely | **PASS** |
| Inactive authority cannot be newly confirmed | **PASS** |

The actor is always the current authenticated WordPress user.

## GET safety

| Check | Result |
| --- | --- |
| Opening detail did not change the authority | **PASS** |
| Did not change origin | **PASS** |
| Did not change detection | **PASS** |
| Did not change lifecycle | **PASS** |

Detail GET may evaluate `LocalAuthoritySenderMatcher`. It does not persist that result.

## 5D.1 regression

| Check | Result |
| --- | --- |
| New ingestion still auto-detects | **PASS** |
| Automatic authority origin = `suggested` | **PASS** |
| EXISTING replay still skips detection | **PASS** |
| Detection and lifecycle remain independent | **PASS** |

## Boundaries

| Check | Result |
| --- | --- |
| No Accept Referral button | **PASS** |
| No referral created | **PASS** |
| No AI or external classifier | **PASS** |
| No Graph or OAuth | **PASS** |
| No production detection re-run action | **PASS** |
| No new portal route | **PASS** |

## Versions

| Check | Result |
| --- | --- |
| Product 1.5.0 | **PASS** |
| Database 2.33.0 | **PASS** |
| Portal rewrite 1.2.8 | **PASS** |
| One additive migration, 2.32.0 → 2.33.0 | **PASS** |

---

## Cleanup

Scoped to `source_provider = fixture`, mailbox identifiers beginning `uat-5d2-`, and the explicitly named UAT 5D.2 authorities and sender rules. No real data was deleted.

| Item | Count |
| --- | --- |
| Inbox fixtures removed | 10 |
| Attachment fixtures removed | 0 |
| Sender-rule fixtures removed | 7 |
| Authority fixtures removed | 4 |
| Temporary access users removed | 0 |
| Remaining scoped Inbox fixtures | 0 |
| Remaining scoped authority fixtures | 0 |

**Result:** **PASS**

---

## Sign-off

| | |
| --- | --- |
| Date | 2026-09-28 |
| Overall | **PASS** |
