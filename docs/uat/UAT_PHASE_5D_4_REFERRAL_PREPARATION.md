# UAT — Phase 5D.4 Referral preparation

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.9  
**Previous checkpoint:** `68f4e8fc26290be4de937dde173c895c809d0fd3` (Phase 5D.3)  
**Branch:** `feature/5d-referral-detection`

**Manual UAT date:** **2026-09-29**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.33.0** · Rewrite **1.2.9**

**UAT method:** Staff Portal preparation was exercised in the browser. A temporary admin-only runner prepared scoped fixtures and service checks, then was removed before this checkpoint. No live UAT page remains.

**Scope:** Staff Portal `/referral-inbox/{id}/prepare/`. Review and validate candidate fields. No referral is created.

**Out of scope:** `ReferralService::create()`, `markAccepted()`, draft storage, schema changes, detection changes, authority confirmation on this screen, Graph, OAuth, AI, develop/main merge, tag, package, deploy. The Phase 5C.2 stash was not applied.

---

## Prepare entry

| Check | Result |
| --- | --- |
| Needs Review fixture opens normally | **PASS** |
| Prepare Referral link visible | **PASS** |
| Prepare route opens successfully | **PASS** |
| Opening prepare causes no mutation | **PASS** |
| No referral created | **PASS** |

## New item

| Check | Result |
| --- | --- |
| Prepare link absent while New | **PASS** |
| Direct prepare URL handled safely | **PASS** |
| Start Review instruction visible | **PASS** |
| Editable form absent | **PASS** |

## Full prefill

| Check | Result |
| --- | --- |
| Client Name = Jane Example | **PASS** |
| Client Email = jane@example.org | **PASS** |
| Client Phone = +44 7700 900123 | **PASS** |
| Referrer Name prefilled | **PASS** |
| Referrer Email prefilled | **PASS** |
| Referrer Organisation prefilled | **PASS** |
| Authority provenance note correct | **PASS** |
| Service Type unselected | **PASS** |
| Supported Living hint visible | **PASS** |
| Referral Source unselected | **PASS** |
| Priority requires explicit selection | **PASS** |
| No referral created | **PASS** |

## Ambiguous candidates

| Check | Result |
| --- | --- |
| Conflicting email input blank | **PASS** |
| Conflicting phone input blank | **PASS** |
| Multiple-values warning visible | **PASS** |
| Alternatives displayed safely | **PASS** |
| No candidate silently chosen | **PASS** |

## No-candidate case

| Check | Result |
| --- | --- |
| Optional candidate fields blank | **PASS** |
| Client Name requires staff entry | **PASS** |
| Service Type requires selection | **PASS** |
| Referral Source requires selection | **PASS** |
| Priority requires selection | **PASS** |
| No defaults invented | **PASS** |

## Cleared authority

| Check | Result |
| --- | --- |
| Referrer Organisation blank | **PASS** |
| Cleared authority not repopulated from sender | **PASS** |
| Authority-review navigation available where appropriate | **PASS** |

## Service and priority hints

| Check | Result |
| --- | --- |
| Supported Living suggestion visible | **PASS** |
| Service Type remains unselected | **PASS** |
| Staff can manually select an active service | **PASS** |
| Urgent suggestion visible | **PASS** |
| Priority not automatically selected | **PASS** |
| Staff must explicitly choose priority | **PASS** |

## Required-field validation

Missing client name, service type, referral source, and priority.

| Check | Result |
| --- | --- |
| Client Name error | **PASS** |
| Service Type error | **PASS** |
| Referral Source error | **PASS** |
| Priority error | **PASS** |
| Inbox stayed needs_review | **PASS** |
| No referral created | **PASS** |

## Valid draft

| Check | Result |
| --- | --- |
| Validate Details succeeds | **PASS** |
| “Referral details are valid and ready for confirmation.” | **PASS** |
| “No referral has been created yet.” | **PASS** |
| Referral count unchanged | **PASS** |
| Inbox remained needs_review | **PASS** |
| linked_referral_id remained NULL | **PASS** |

## Sticky staff correction

Initial candidate: Jane Example. Staff changed it to Jane Corrected, then another validation error was triggered.

| Check | Result |
| --- | --- |
| Validation error shown | **PASS** |
| Jane Corrected remained in the input | **PASS** |
| Extractor did not overwrite the human correction | **PASS** |

## Invalid email

| Check | Result |
| --- | --- |
| Malformed email rejected | **PASS** |
| Other submitted values remained sticky | **PASS** |
| No referral created | **PASS** |

## Forged or inactive service type

| Check | Result |
| --- | --- |
| Invalid or inactive service_type_id rejected | **PASS** |
| No referral created | **PASS** |
| No SQL or error dump | **PASS** |

## Assignment

| Check | Result |
| --- | --- |
| Valid assignee selectable | **PASS** |
| Default = Unassigned | **PASS** |
| Forged or nonexistent assignee rejected safely | **PASS** |
| User without ASSIGN_REFERRALS cannot force assigned_to | **PASS** |

## GET non-mutation

| Check | Result |
| --- | --- |
| Lifecycle unchanged | **PASS** |
| reviewed_by unchanged | **PASS** |
| reviewed_at unchanged | **PASS** |
| Detection unchanged | **PASS** |
| Authority unchanged | **PASS** |
| No referral activity | **PASS** |
| No referral created | **PASS** |
| No draft persisted | **PASS** |

## Stale state

Prepare was opened while the item was needs_review. Another tab moved it to a terminal state. The stale validation POST was then submitted.

| Check | Result |
| --- | --- |
| Friendly stale-state message | **PASS** |
| Terminal state preserved | **PASS** |
| No referral created | **PASS** |
| No authority change | **PASS** |
| No detection change | **PASS** |
| No SQL or error dump | **PASS** |

## Access control

| Check | Result |
| --- | --- |
| Platform Administrator allowed | **PASS** |
| Referral Manager allowed | **PASS** |
| Care Coordinator allowed | **PASS** |
| Assessor denied | **PASS** |
| Support Worker denied | **PASS** |
| Direct prepare URL protected | **PASS** |
| Nonce failure denied | **PASS** |
| Unauthorised POST denied | **PASS** |

## Responsive and accessibility

| Check | Result |
| --- | --- |
| 1440px | **PASS** |
| 1280px | **PASS** |
| 1024px | **PASS** |
| 768px | **PASS** |
| 375px | **PASS** |
| No page-level horizontal overflow | **PASS** |
| Inputs usable on mobile | **PASS** |
| Candidate helper text wraps | **PASS** |
| Context panel readable | **PASS** |
| Required fields clearly identified | **PASS** |
| Validation summary readable | **PASS** |
| Field errors readable | **PASS** |
| Keyboard and focus behaviour usable | **PASS** |

## No persistence

| Check | Result |
| --- | --- |
| No draft table row | **PASS** |
| No candidate table row | **PASS** |
| No wp_options draft | **PASS** |
| No transient draft | **PASS** |
| No session or cookie draft | **PASS** |
| No referral activity | **PASS** |
| No notification | **PASS** |
| No referral created | **PASS** |

## Production body-preview compatibility

`ReferralInboxService::create()` stores `body_preview` through `bound_plain_text()`. Line breaks become spaces, so a labelled message is kept as one line:

`Client Name: Jane Example Client Email: jane@example.org Client Phone: +44 7700 900123`

The first 5D.4 extractor only accepted a label at the start of a line. That could not read real stored Inbox previews. The permanent fix is in `ReferralInboxCandidateExtractor`. It recognises supported labels in the flattened preview, requires a label at the start or after whitespace, requires `:` or `-`, matches a longer label before a shorter overlapping label, and ends a value at the next recognised label. Multiline input, ambiguity, and false-positive protections still hold.

| Check | Result |
| --- | --- |
| Flattened Client Name extraction | **PASS** |
| Flattened Client Email extraction | **PASS** |
| Flattened Client Phone extraction | **PASS** |
| Flattened ambiguous email detection | **PASS** |
| Flattened ambiguous phone detection | **PASS** |

The temporary runner workaround that wrote line breaks back after `create()` was removed. The retest used the real stored preview.

## Final focused recheck

| Check | Result |
| --- | --- |
| Jane Example prefilled | **PASS** |
| jane@example.org prefilled | **PASS** |
| +44 7700 900123 prefilled | **PASS** |
| Ambiguous email blank | **PASS** |
| Ambiguous phone blank | **PASS** |
| Ambiguity warnings visible | **PASS** |
| Valid draft succeeds | **PASS** |
| Ready-for-confirmation message | **PASS** |
| No-referral-created message | **PASS** |
| Jane Corrected survives another validation error | **PASS** |

## Runner recheck

| Check | Result |
| --- | --- |
| Client name prefilled | **PASS** |
| Client email prefilled | **PASS** |
| Client phone prefilled | **PASS** |
| Ambiguous email and phone stay blank | **PASS** |
| All other temporary service checks | **PASS** |

## Regression

| Check | Result |
| --- | --- |
| 5D.1 detection | **PASS** |
| 5D.2 authority review | **PASS** |
| 5D.3 extraction, including flattened stored previews | **PASS** |
| Inbox list and detail, aside from the Prepare Referral link | **PASS** |
| Start Review, Ignore, Duplicate, error recovery | **PASS** |
| Existing referrals | **PASS** |
| Local Authority directory | **PASS** |
| Microsoft connection tables | **PASS** |
| 5C.2 stash untouched | **PASS** |

## Versions

| Check | Result |
| --- | --- |
| Product 1.5.0 | **PASS** |
| Database 2.33.0 | **PASS** |
| Portal rewrite 1.2.9 | **PASS** |
| No migration | **PASS** |
| Exactly one new portal route | **PASS** |

---

## Cleanup

Scoped to mailbox identifiers beginning `uat-5d4-` and Local Authority notes `uat-5d4-fixture`. No real Inbox, referral, or authority data was removed.

| Item | Count |
| --- | --- |
| Inbox fixtures removed | 11 |
| Attachment fixtures removed | 0 |
| Local Authority fixtures removed | matching UAT rows removed |
| Referrals created | 0 |
| Remaining `uat-5d4-` Inbox fixtures | 0 |

**Result:** **PASS**

---

## Sign-off

| | |
| --- | --- |
| Date | 2026-09-29 |
| Overall | **PASS** |
