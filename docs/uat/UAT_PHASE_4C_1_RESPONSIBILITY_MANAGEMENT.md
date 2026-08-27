# UAT — Phase 4C.1 Referral Responsibility Management

**Product:** 1.4.0  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** **1.2.7** (from 1.2.6)  
**Baseline checkpoint:** `5ee28eb7490cabf2e522182e17ed615f690cccc5` (Phase 4B.2.5)

**Scope:** Staff Portal Responsibilities panel and authorised manage form for referral owner (`assigned_to`), champion (`champion_user_id`), and transition lead (`transition_lead_user_id`).

**Out of scope:** Emails, workflow-stage changes, meetings, assessment scheduling, Management Dashboard, schema, new capabilities, Phase 4C.2.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Field mapping

| Label | Column |
| --- | --- |
| Referral owner | `assigned_to` |
| Champion | `champion_user_id` |
| Transition lead | `transition_lead_user_id` |

## Eligibility

Eligible users are valid WordPress users with the existing `jmrs_view_referrals` capability under JMRS staff conventions, via `UserProvider::is_assignable()` / `get_assignable_users()`. The selector does not expose every WordPress account. Meeting-attendee eligibility was not substituted.

## Access

| Role | View names | Manage |
| --- | --- | --- |
| JM Administrator / Referral Manager / Care Coordinator / WP admin | Yes (if referral visible) | Yes via `can_assign_referral_responsibilities` on active referrals |
| Assessor | Yes (if referral visible) | No |
| Support Worker | Per referral visibility | No; membership as champion/lead grants no access |

Archived referrals: read-only names; manage route denied (non-leaking).

Same person may hold owner + champion + transition lead.

Deleted stored users display as **Unavailable user**; values are not auto-cleared.

---

## Referral used and baseline restoration

| Item | Value |
| --- | --- |
| Referral ID | **7** (retained) |
| Temporary UAT changes | All three roles assigned; same person in multiple roles; owner changed; champion cleared; transition lead changed; invalid-user / mass-assignment / archived enforcement exercised |
| Baseline restoration | **Restored through the Staff Portal** to the original pre-UAT responsibility values for `assigned_to`, `champion_user_id`, and `transition_lead_user_id` |
| Original user IDs / names | **Not captured** in this document (do not invent) |
| Workflow stage / status | Unchanged |
| Meetings / assessment | Unchanged |
| Activity Timeline | Truthful UAT activity entries may remain; not deleted or rewritten |

---

## Activation and routing

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Responsibilities panel displayed | **PASS** |
| Manage responsibilities route opened | **PASS** |
| Portal rewrite 1.2.7 working | **PASS** |
| Referral detail regression | **PASS** |

---

## Initial display

| Check | Result |
| --- | --- |
| Initial Referral owner displayed correctly | **PASS** |
| Initial Champion displayed correctly | **PASS** |
| Initial Transition lead displayed correctly | **PASS** |
| Unassigned fallback displayed where applicable | **PASS** |
| No staff emails displayed | **PASS** |
| No WordPress user IDs displayed | **PASS** |
| No role or capability slugs displayed | **PASS** |

---

## Assignment

| Check | Result |
| --- | --- |
| Referral owner assigned | **PASS** |
| Champion assigned | **PASS** |
| Transition lead assigned | **PASS** |
| Three responsibility changes saved together | **PASS** |
| Correct activity events created | **PASS** |
| No emails sent | **PASS** |
| Referral workflow stage unchanged | **PASS** |
| Referral status unchanged | **PASS** |
| Meeting and assessment data unchanged | **PASS** |

---

## Same user in multiple roles

| Check | Result |
| --- | --- |
| Same staff member accepted as Referral owner | **PASS** |
| Same staff member accepted as Champion | **PASS** |
| Same staff member accepted as Transition lead | **PASS** |
| Same person allowed in all three roles simultaneously | **PASS** |
| No false duplicate validation | **PASS** |
| Only actually changed fields created activity | **PASS** |

---

## Change and clear

| Check | Result |
| --- | --- |
| Referral owner changed | **PASS** |
| Champion cleared to Unassigned | **PASS** |
| Transition lead changed | **PASS** |
| Correct activity actions created | **PASS** |
| Unchanged responsibility fields created no activity | **PASS** |

---

## No-op

| Check | Result |
| --- | --- |
| Identical responsibility submission accepted safely | **PASS** |
| “No changes were made” behaviour | **PASS** |
| No unnecessary activity created | **PASS** |
| No duplicate activity after refresh | **PASS** |

---

## Validation and tampering

| Check | Result |
| --- | --- |
| Invalid staff user ID rejected | **PASS** |
| Invalid user created no activity | **PASS** |
| Caller-controlled referral_id blocked | **PASS** |
| workflow_stage_id tampering blocked | **PASS** |
| Referral status tampering blocked | **PASS** |
| Priority tampering blocked | **PASS** |
| archived_at tampering blocked | **PASS** |
| Tampering created no misleading activity | **PASS** |
| Validated route referral ID remained authoritative | **PASS** |
| Raw request array was not persisted | **PASS** |

---

## Assessor

| Check | Result |
| --- | --- |
| Responsibility display names visible | **PASS** |
| Manage responsibilities action hidden | **PASS** |
| Direct management route denied | **PASS** |
| Selector form not exposed | **PASS** |
| No staff email or WordPress ID exposed | **PASS** |

---

## Access grants

| Check | Result |
| --- | --- |
| Champion membership granted no referral access | **PASS** |
| Champion membership granted no meeting access | **PASS** |
| Champion membership granted no responsibility-management permission | **PASS** |
| Transition-lead membership granted no referral access | **PASS** |
| Transition-lead membership granted no meeting access | **PASS** |
| Transition-lead membership granted no responsibility-management permission | **PASS** |
| No WordPress role or capability was changed | **PASS** |

---

## Archived referral

| Check | Result |
| --- | --- |
| Responsibility names remained readable | **PASS** |
| Manage responsibilities action hidden | **PASS** |
| Direct route mutation denied | **PASS** |
| No responsibility values changed during denial | **PASS** |
| Denied attempt created no activity | **PASS** |
| Referral restored successfully | **PASS** |
| Responsibility values remained intact after restoration | **PASS** |

---

## Responsive design (~375px)

| Check | Result |
| --- | --- |
| No page-level horizontal overflow | **PASS** |
| Long staff names wrapped safely | **PASS** |
| Selectors remained inside the form | **PASS** |
| Buttons remained usable | **PASS** |
| Responsibilities panel remained readable | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| No emails | **PASS** |
| workflow_stage_id unchanged | **PASS** |
| Referral status unchanged | **PASS** |
| Meeting records unchanged | **PASS** |
| Meeting attendees unchanged | **PASS** |
| Assessment scheduling unchanged | **PASS** |
| Management Dashboard unchanged | **PASS** |
| VisualStageMap unchanged | **PASS** |

---

## Service API (confirmed)

- `get_for_referral()`
- `update_responsibilities()`
- `assign_champion()` / `clear_champion()`
- `assign_transition_lead()` / `clear_transition_lead()`

## Activity actions (confirmed)

| Area | Actions |
| --- | --- |
| Owner | `assigned`, `reassigned` (clear uses `reassigned` to Unassigned) |
| Champion | `champion_assigned`, `champion_reassigned`, `champion_unassigned` |
| Transition lead | `transition_lead_assigned`, `transition_lead_reassigned`, `transition_lead_unassigned` |

---

## Sign-off

| Role | Name | Date | Outcome |
| --- | --- | --- | --- |
| Tester | Staging focused UAT | 2026-08-27 | **PASS** |
| Reviewer | | 2026-08-27 | **PASS** |
