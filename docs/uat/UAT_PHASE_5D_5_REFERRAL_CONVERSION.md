# UAT — Phase 5D.5 Referral conversion

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.9  
**Previous checkpoint:** `f6cb5031ee47642e9fea7c80665f47f7e7848e45` (Phase 5D.4)  
**Branch:** `feature/5d-referral-detection`

**Manual UAT date:** **2026-09-29**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.33.0** · Rewrite **1.2.9**

**UAT method:** Staff Portal Create Referral was exercised in the browser. A temporary admin-only runner created scoped fixture referrals and proved rollback, then cleanup removed only those recorded fixture rows. The runner was removed before this checkpoint. No live UAT page remains.

**Scope:** Human-confirmed Create Referral on the existing `/referral-inbox/{id}/prepare/` route. Exactly one referral and an accepted Inbox link, or no change.

**Out of scope:** Schema change, new route, new `submission_channel`, Graph, OAuth, webhook, delta, mailbox polling, AI, draft storage, develop/main merge, tag, package, deploy. The Phase 5C.2 stash was not applied.

**Observed successful referral number:** `JM-20260929-0001` (fixture removed during cleanup).

---

## Preflight

| Check | Result |
| --- | --- |
| `referrals` engine InnoDB | **PASS** |
| `referral_activity` engine InnoDB | **PASS** |
| `referral_stage_history` engine InnoDB | **PASS** |
| `referral_inbox` engine InnoDB | **PASS** |
| Referral-number advisory lock acquire/release | **PASS** |
| Lock released after preflight | **PASS** |

## Successful conversion

| Check | Result |
| --- | --- |
| Exactly one referral created | **PASS** |
| Inbox status accepted | **PASS** |
| `linked_referral_id` points at that referral | **PASS** |
| `accepted_by` is the current user | **PASS** |
| `accepted_at` populated | **PASS** |
| Referral number created | **PASS** |
| View Referral works | **PASS** |

## Activity

| Check | Result |
| --- | --- |
| `created` | **PASS** |
| `pipeline_started` where a normal create writes it | **PASS** |
| `assigned` where a normal create writes it | **PASS** |
| No custom Inbox conversion activity | **PASS** |

## Atomicity

| Check | Result |
| --- | --- |
| Controlled insert failure left no referral | **PASS** |
| Inbox stayed `needs_review` | **PASS** |
| `linked_referral_id` stayed NULL | **PASS** |
| No notification before commit | **PASS** |
| Accept failure after an in-transaction insert rolled back | **PASS** |
| New referral absent after that rollback | **PASS** |
| Created activity rolled back | **PASS** |
| Pipeline/stage history rolled back | **PASS** |
| Inbox stayed `needs_review` | **PASS** |
| No notification attempted | **PASS** |

## Commit boundary and notification

| Check | Result |
| --- | --- |
| Order is transaction, row lock, classify, number lock, revalidation, database create, accept/link, commit, release lock, then notification | **PASS** |
| Assignment notification only after commit | **PASS** |
| No email before a successful commit | **PASS** |
| Fixture harness did not email a real service user | **PASS** |
| Post-commit notification failure left the referral and the link | **PASS** |
| Warning was non-blocking | **PASS** |
| Retry after that failure created no second referral | **PASS** |

## Idempotency and later visits

| Check | Result |
| --- | --- |
| Second submit returned `ALREADY_CONVERTED` for the same Inbox item | **PASS** |
| Existing referral returned | **PASS** |
| No second referral | **PASS** |
| Stale second-tab submit showed the existing linked referral | **PASS** |
| Prepare after success hid the editable form | **PASS** |
| Already-converted message visible | **PASS** |
| View Referral available | **PASS** |
| Opening prepare created no new referral | **PASS** |

## Validation, confirmation, and terminal states

| Check | Result |
| --- | --- |
| Invalid required values blocked, no referral, Inbox stayed `needs_review`, no notification | **PASS** |
| Valid form without the confirmation checkbox blocked, no referral, Inbox unchanged | **PASS** |
| Ignored/terminal Inbox before Create stayed terminal and created no referral | **PASS** |
| `needs_review` with an existing link returned `INCONSISTENT_LINK` and did not clear the link | **PASS** |
| `accepted` with a NULL link stayed inconsistent, was not repaired, and created no referral | **PASS** |

## Assignment and security

| Check | Result |
| --- | --- |
| Assigned conversion stored a valid assignee | **PASS** |
| Assignment notification only after commit | **PASS** |
| User without `ASSIGN_REFERRALS`: forged `assigned_to` ignored, stored value `0`, no assignment email | **PASS** |
| Forged actor ignored; actor is `get_current_user_id()` | **PASS** |
| Forged `linked_referral_id` ignored | **PASS** |
| Forged status ignored | **PASS** |
| Forged `submission_channel` ignored; stored channel is `admin` | **PASS** |

## Preservation

| Check | Result |
| --- | --- |
| Referral 10 unchanged | **PASS** |
| Existing referrals unchanged except temporary UAT rows | **PASS** |
| Phase 5D.1 detection unchanged | **PASS** |
| Phase 5D.2 authority review unchanged | **PASS** |
| Phase 5D.3 extraction unchanged | **PASS** |
| Phase 5D.4 preparation and validation unchanged | **PASS** |
| Local Authority directory unchanged | **PASS** |
| Microsoft connection configuration unchanged | **PASS** |
| Phase 5C.2 stash still present | **PASS** |

## Versions

| Check | Result |
| --- | --- |
| Product 1.5.0 | **PASS** |
| Database 2.33.0 | **PASS** |
| Portal rewrite 1.2.9 | **PASS** |
| No migration | **PASS** |
| No schema change | **PASS** |
| No new route | **PASS** |

## Cleanup

| Item | Count |
| --- | --- |
| Fixture referrals removed | 6 |
| Matching fixture Inbox rows removed | 6 |
| Remaining `uat-5d5-` Inbox rows | 0 |
| Remaining `uat-5d5-` referrals | 0 |

**Cleanup result:** **PASS**

Referral 10 was never eligible. Deletion was limited to referral ids recorded by the temporary runner and linked from `source_provider = fixture` with `mailbox_identifier` like `uat-5d5-%`. Cleanup deleted the tracking option `jmrs_uat_5d5_referral_ids`.

**Overall:** **PASS**
