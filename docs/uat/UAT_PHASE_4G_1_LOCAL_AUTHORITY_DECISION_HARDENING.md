# UAT — Phase 4G.1 Local Authority Decision Hardening and Operational Metrics

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `c6c7d072e9cd7597df6e86aecd9226821ecf13ff` (Phase 4F.1)

**Scope:** Harden existing Phase 3F record-once LA Decision; display stored notes on read-only panel; Operations awaiting + outcome counts; focused UAT. Do **not** rebuild LA Decision.

**Out of scope:** Correction/reconsideration/reopen; authority-name field; decision-document linkage; approved-amount field; new LA email; UNIQUE(`referral_id`); placement transition; care commencement; Package Costing redesign; Assessor visibility product change.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Definitions

| Item | Rule |
| --- | --- |
| Current package | Latest package-cost row (`MAX(id)`); must be `sent` |
| Current decision | Latest LA-decision row (`MAX(id)`) where reads require it |
| Record-once | Normal runtime allows one decision per referral |
| Approved | → pipeline `transition_planning`; status `in_progress` |
| Declined | → pipeline `declined`; status `cancelled` |
| Not proceeding | → pipeline `not_proceeding`; status `cancelled` |
| Terminal | After record: read-only; form hidden; re-record denied |
| Dashboard awaiting | Pipeline stage `awaiting_la_decision`; non-archived; scoped |
| Dashboard outcomes | Latest decision `approved` / `declined` / `not_proceeding` |
| Operations privacy | No notes, funding, references, package totals, or client details |
| Assessor panel | **EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION** |
| UNIQUE limitation | No DB `UNIQUE(referral_id)` — accepted application-level limitation |

---

## Activation

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Product remained 1.4.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |

---

## Test referral

| Item | Value |
| --- | --- |
| Staging referral ID | **10** |
| Package status before decision | Sent |
| Initial pipeline | `awaiting_la_decision` |
| Existing LA decision before UAT | None |
| Final decision | **approved** |
| Final pipeline | `transition_planning` |
| Final referral status | `in_progress` |
| Package after decision | Remained **Sent** |
| Retention | Retained for staging transition-planning work |

Package IDs, decision IDs, and user IDs were **not captured** (do not invent).

---

## Panel and GET behaviour

| Check | Result |
| --- | --- |
| LA Decision panel opened successfully | **PASS** |
| Current Sent package prerequisite confirmed | **PASS** |
| Decision form visible before recording | **PASS** |
| Merely opening the panel created no decision | **PASS** |
| Refresh before decision created no activity | **PASS** |
| GET caused no pipeline transition | **PASS** |
| GET caused no referral-status change | **PASS** |
| GET caused no email | **PASS** |

---

## Archive protection (pre-decision)

| Check | Result |
| --- | --- |
| Referral 10 archived before final decision | **PASS** |
| Existing Sent package remained readable to authorised commercial user | **PASS** |
| LA Decision form hidden while archived | **PASS** |
| Direct archived decision POST denied | **PASS** |
| No decision row created while archived | **PASS** |
| No activity created | **PASS** |
| No pipeline transition | **PASS** |
| No referral-status change | **PASS** |
| No email caused by denied request | **PASS** |
| Referral restored successfully | **PASS** |
| LA Decision form returned after restore | **PASS** |
| Package remained Sent | **PASS** |
| Pipeline remained `awaiting_la_decision` before final approval | **PASS** |

---

## Access — Assessor

| Check | Result |
| --- | --- |
| Existing package and decision-area information remained readable under current policy | **PASS** |
| LA Decision form hidden | **PASS** |
| Submission controls hidden | **PASS** |
| Direct decision mutation denied | **PASS** |
| No Management Dashboard access | **PASS** |
| Assessor assignment granted no extra commercial permission | **PASS** |

**EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION:** Assessor visibility of package and LA-decision details remains an existing product behaviour requiring future JM confirmation.

---

## Access — Support Worker

| Check | Result |
| --- | --- |
| LA Decision information inaccessible | **PASS** |
| Package document inaccessible | **PASS** |
| Management Dashboard inaccessible | **PASS** |
| Direct decision mutation denied | **PASS** |

---

## Responsibility membership

| Check | Result |
| --- | --- |
| Owner membership granted no additional LA-decision permission | **PASS** |
| Champion membership granted no additional LA-decision permission | **PASS** |
| Transition Lead membership granted no additional LA-decision permission | **PASS** |
| Package-preparer membership granted no decision permission | **PASS** |

---

## Invalid decision tests

| Check | Result |
| --- | --- |
| Unknown decision rejected | **PASS** |
| Markup/script decision rejected safely | **PASS** |
| Array decision rejected safely | **PASS** |
| Blank decision rejected | **PASS** |
| No invalid decision row created | **PASS** |
| Pipeline remained `awaiting_la_decision` during invalid tests | **PASS** |
| Package remained Sent | **PASS** |
| Invalid attempts created no activity | **PASS** |
| Invalid attempts caused no status-change email | **PASS** |
| Raw malicious input was not rendered unsafely | **PASS** |

---

## Mass-assignment and tampering

Crafted fields attempted: `referral_id`, `package_cost_id`, `recorded_by`, pipeline stage, referral status, `created_at`.

| Check | Result |
| --- | --- |
| `referral_id` tampering blocked | **PASS** |
| Decision remained associated with referral 10 | **PASS** |
| `package_cost_id` tampering blocked | **PASS** |
| Current Sent package selected server-side | **PASS** |
| `recorded_by` tampering blocked | **PASS** |
| Actor came from the authenticated user | **PASS** |
| Pipeline-stage tampering blocked | **PASS** |
| Referral-status tampering blocked | **PASS** |
| `created_at` tampering blocked | **PASS** |
| No misleading activity created | **PASS** |
| No arbitrary caller value persisted | **PASS** |

---

## Approved decision

| Field | Value |
| --- | --- |
| Decision | `approved` |
| Decision date | 2026-08-27 |
| Funding confirmed | yes |
| Funding reference | `UAT-FUND-4G1-001` |
| Decision reference | `UAT-LA-4G1-001` |
| Notes | Approved for Phase 4G.1 staging verification. Transition planning may now begin. |

| Check | Result |
| --- | --- |
| Approved decision saved successfully | **PASS** |
| Current Sent package linked server-side | **PASS** |
| Funding value saved correctly | **PASS** |
| Funding reference saved correctly | **PASS** |
| Decision reference saved correctly | **PASS** |
| Notes saved correctly | **PASS** |
| Exactly one `la_decision_approved` activity created | **PASS** |
| Pipeline advanced exactly once to `transition_planning` | **PASS** |
| Referral status became `in_progress` | **PASS** |
| Package remained Sent and unchanged | **PASS** |
| No placement record created | **PASS** |
| No care commencement created | **PASS** |

---

## Read-only terminal panel

| Check | Result |
| --- | --- |
| Recorded/read-only notice displayed | **PASS** |
| Decision displayed as Approved | **PASS** |
| Decision date displayed | **PASS** |
| Funding confirmed displayed | **PASS** |
| Funding reference displayed | **PASS** |
| Decision reference displayed | **PASS** |
| Notes displayed safely | **PASS** |
| Notes retained readable line breaks | **PASS** |
| Recorded by displayed safely | **PASS** |
| Recorded date displayed | **PASS** |
| No raw HTML rendered | **PASS** |
| No raw package ID displayed | **PASS** |
| No raw decision ID displayed | **PASS** |
| LA Decision form hidden | **PASS** |
| Submit button hidden | **PASS** |
| No editable hidden decision form rendered | **PASS** |

---

## Duplicate and direct-edit protection

| Check | Result |
| --- | --- |
| Page refresh created no second decision | **PASS** |
| Repeated refresh created no duplicate approval activity | **PASS** |
| Browser back/resubmit created no duplicate decision | **PASS** |
| Direct second record attempt denied | **PASS** |
| Attempted Declined overwrite rejected | **PASS** |
| Attempted reference overwrite rejected | **PASS** |
| Attempted notes overwrite rejected | **PASS** |
| Attempted `package_cost_id` overwrite rejected | **PASS** |
| Original Approved decision remained unchanged | **PASS** |
| Pipeline remained `transition_planning` | **PASS** |
| Referral status remained `in_progress` | **PASS** |
| Package remained Sent | **PASS** |
| No duplicate pipeline transition | **PASS** |
| No duplicate status change | **PASS** |
| No activity created by denied duplicate request | **PASS** |
| No email caused by denied duplicate request | **PASS** |

---

## Dashboard metrics

| Check | Result |
| --- | --- |
| Operations Local Authority Decisions section opened | **PASS** |
| Awaiting LA Decision count decreased by 1 | **PASS** |
| Approved Decisions count increased by 1 | **PASS** |
| Declined Decisions count unchanged | **PASS** |
| Not Proceeding count unchanged | **PASS** |
| Latest decision row selected using `MAX(id)` | **PASS** |
| Older rows not double-counted | **PASS** |
| Archived referrals excluded | **PASS** |
| Referral scope applied | **PASS** |
| Dashboard GET remained read-only | **PASS** |

---

## Privacy

Operations dashboard did **not** display: `UAT-FUND-4G1-001`; `UAT-LA-4G1-001`; decision notes; funding details; package total; package document filename; client name; client contacts; raw package IDs; raw decision IDs.

| Check | Result |
| --- | --- |
| Operations privacy | **PASS** |
| Activity privacy | **PASS** |
| Notes absent from activity descriptions | **PASS** |
| References absent from activity descriptions | **PASS** |
| Funding details absent from activity descriptions | **PASS** |
| Package amounts absent from decision activity | **PASS** |
| Raw IDs absent from activity descriptions | **PASS** |

---

## Responsive and accessibility (~375px)

| Check | Result |
| --- | --- |
| Decision panel remained inside page container | **PASS** |
| Long notes wrapped safely | **PASS** |
| Long references wrapped safely | **PASS** |
| Dashboard cards stacked correctly | **PASS** |
| Outcome shown using text rather than colour only | **PASS** |
| Read-only state clearly communicated | **PASS** |
| Labels remained readable | **PASS** |
| Buttons remained keyboard accessible before decision | **PASS** |
| No page-level horizontal overflow | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| Package Cost row unchanged except existing relationship use | **PASS** |
| Package remained Sent | **PASS** |
| Package total unchanged | **PASS** |
| Package document unchanged | **PASS** |
| Assessment unchanged | **PASS** |
| Assessment outcome unchanged | **PASS** |
| Meeting records unchanged | **PASS** |
| Meeting attendees unchanged | **PASS** |
| `assigned_to` unchanged | **PASS** |
| `champion_user_id` unchanged | **PASS** |
| `transition_lead_user_id` unchanged | **PASS** |
| Responsibilities unchanged | **PASS** |
| No placement record created | **PASS** |
| No occupancy record created | **PASS** |
| No care commencement created | **PASS** |

---

## Declined decision workflow

**NOT RUN — CODE REVIEWED**

| Item | Result |
| --- | --- |
| Decision value allowlisted | Confirmed |
| Current Sent package required | Confirmed |
| Pipeline → `declined` | Confirmed |
| Referral status → `cancelled` | Confirmed |
| Record-once | Confirmed |
| Duplicate request denied | Confirmed |
| Activity `la_decision_declined` | Confirmed |

---

## Not Proceeding workflow

**NOT RUN — CODE REVIEWED**

| Item | Result |
| --- | --- |
| Decision value allowlisted | Confirmed |
| Current Sent package required | Confirmed |
| Pipeline → `not_proceeding` | Confirmed |
| Referral status → `cancelled` | Confirmed |
| Record-once | Confirmed |
| Duplicate request denied | Confirmed |
| Activity `referral_not_proceeding` | Confirmed |

---

## Status-change email

**NOT RUN — CODE REVIEWED**

| Item | Result |
| --- | --- |
| No new LA-decision email type | Confirmed |
| Existing `status-changed` path remains | Confirmed |
| Notification only after lifecycle status changes | Confirmed |
| Decision transaction does not depend on email delivery | Confirmed (side effects after COMMIT) |
| Notes / funding / decision refs / package total not in email | Confirmed |
| Package document not attached | Confirmed |
| Denied/duplicate requests do not send email | Confirmed |

---

## Sign-off

| Item | Value |
| --- | --- |
| Staging referral ID | **10** |
| Tester | Staging focused UAT |
| Date | **2026-08-27** |
| Overall | **PASS** |
| Notes | Declined / Not Proceeding / status-change email: **NOT RUN — CODE REVIEWED**. Referral 10 retained on `transition_planning` for later staging work. Assessor package/LA visibility still requires future JM confirmation. |
