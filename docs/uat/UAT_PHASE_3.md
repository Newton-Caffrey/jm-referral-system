# UAT — Phase 3 Acquisition Pipeline (Final)

Manual Pass/Fail acceptance for **Phase 3** (pipeline → acquisition reporting).

**Versions:** Product `1.3.0` · DB `2.28.0` · Portal rewrite `1.2.1`

Related: [UAT package index](README.md) · [PACKAGING.md](../PACKAGING.md) · [WORKFLOWS.md](../developer/WORKFLOWS.md)

| Field | Value |
| --- | --- |
| Tester | |
| Environment | |
| Date | |
| Product version | `1.3.0` (expected) |
| DB version | `2.28.0` (expected) |
| Portal rewrite | `1.2.1` (expected) |
| Build / branch | |

Fill Actual Result / Pass-Fail during testing. Store evidence privately under `uat-evidence/` (gitignored).

---

## Success path (end-to-end)

| Step | Action | Expected pipeline stage | Expected referral status |
| --- | --- | --- | --- |
| 1 | Public referral submitted | Interest Response Required | New (typical) |
| 2 | Interest response required (default) | Interest Response Required | — |
| 3 | Express Interest | Assessment to Schedule | In Progress (typical) |
| 4 | Assessment to Schedule | Assessment to Schedule | — |
| 5 | Schedule Assessment | Assessment Scheduled | — |
| 6 | Complete Suitable assessment | Package Cost to Prepare | — |
| 7 | Package Cost to Prepare | Package Cost to Prepare | — |
| 8 | Prepare Package Cost | Package Cost to Prepare | — |
| 9 | Send Package Cost by Email | Awaiting LA Decision | — |
| 10 | Awaiting LA Decision | Awaiting LA Decision | — |
| 11 | Record Approved Decision | Transition Planning | — |
| 12 | Transition Planning | Transition Planning | — |
| 13 | Select care setting | Transition Planning | — |
| 14 | Supported Living placement **or** Own Home readiness | Transition Planning | — |
| 15 | Confirm Care Commenced | Placement / Care Commenced | **In Progress** (not Completed) |
| 16 | Pipeline = Care Commenced | Care Commenced | In Progress |
| 17 | Care operations continue | Care Commenced | In Progress |

---

## Branch paths

### Assessment Not Suitable

Assessment Scheduled → Assessment Outcome Review → Mark Not Proceeding → pipeline **Not Proceeding**, referral status **Cancelled**.

### LA Declined

Awaiting LA Decision → record Declined → pipeline **Declined**, status **Cancelled**.

### Early Not Proceeding

Any eligible acquisition active stage → Mark Not Proceeding → **Not Proceeding** / **Cancelled**.

### Transition Stop

Transition Planning → Not Proceeding → **Not Proceeding** / **Cancelled**.

**Care Commenced must not expose acquisition Not Proceeding.**

---

## Permission matrix (Phase 3)

| Capability / surface | JM Administrator | Referral Manager | Care Coordinator | Assessor | Support Worker |
| --- | --- | --- | --- | --- | --- |
| Express Interest | Yes | Yes | Per AccessPolicy / commercial policy | No (unless granted) | No |
| Assessment scheduling | Yes | Yes | Per policy | Typically yes (assess) | No |
| Assessment completion | Yes | Yes | Per policy | Yes | No |
| Package Cost | Yes | Yes | Per commercial policy | No | No |
| LA Decision | Yes | Yes | Per commercial policy | No | No |
| Mark Not Proceeding | Yes | Yes | Per policy | No (unless granted) | No |
| Care Commencement | Yes | Yes | Per policy | No | No |
| Pipeline Dashboard | Yes | Yes | Yes (commercial surface) | No full commercial | Scoped KPIs only |
| Acquisition Reports (`jmrs_view_reports`) | Yes | Yes | Yes (existing reports policy) | **No** | **No** |

Do not grant documentation permissions the code does not provide. Reconfirm against Roles / Capabilities / AccessPolicy.

---

## Email regression

| Test | Expected |
| --- | --- |
| Express Interest email | Sends only on Express Interest action |
| Package Cost email + current attachment | Exact current Package Cost document; private source |
| Status-change email to assignee | Only where existing lifecycle triggers it |
| Migration / Reports / Dashboard / Needs Attention | **No** emails |

---

## Private document regression

| Test | Expected |
| --- | --- |
| Package Cost attachment source | Remains private storage |
| Temp mail copies | Cleaned after success/failure |
| Reports / Acquisition CSV | No private paths, temp paths, or storage keys |
| Download protection | Unchanged |

---

## A. Report cohort

| ID | Steps | Expected |
| --- | --- | --- |
| 3I-A01 | Set date From/To on Reports | Cohort = referrals with `created_at` in range |
| 3I-A02 | Milestone after cohort end date | Still counted for that referral cohort |
| 3I-A03 | Legacy / pre-pipeline in range (including Admin override onto a canonical stage without `created` history) | Shown as Legacy count; **not** in funnel denominators; no fabricated milestones |
| 3I-A04 | Archive a Care Commenced referral | Outcome retained in acquisition history |

## B. Funnel

| ID | Check |
| --- | --- |
| 3I-B01 | Received, Interest, Assessment Completed, Package Cost Sent, Approved, Declined, Not Proceeding, Care Commenced, Still Active counts |
| 3I-B02 | % of Received correct; zero denominator shows — |
| 3I-B03 | Declined / Not Proceeding are branches (not forced into linear funnel) |

## C. Timing

| ID | Metric | Rule |
| --- | --- | --- |
| 3I-C01 | Received → Interest | Needs `created_at` + `interest_expressed_at` |
| 3I-C02 | Package Cost Sent → LA Decision | Linked `package_cost_id` + `sent_at` + `decision_at` |
| 3I-C03 | LA Approval → Care Commenced | Approved `decision_at` + `care_commenced_at` |
| 3I-C04 | Received → Care Commenced | Only commenced referrals |
| 3I-C05 | Missing timestamps | Not Available / omitted — never guessed from `updated_at` or notes |

## D. Assessments

| ID | Expected |
| --- | --- |
| 3I-D01 | Pending excluded from completed funnel count |
| 3I-D02 | Suitable / Suitable With Conditions / Not Suitable counted separately |

## E. Package Cost / Funding

| ID | Expected |
| --- | --- |
| 3I-E01 | Current (latest-by-id) Package Cost only — no superseded double-count |
| 3I-E02 | Proposed Package Value clearly labelled (not revenue) |
| 3I-E03 | Funding Yes / No / Not Recorded on approved decisions only |

## F. CSV

| ID | Expected |
| --- | --- |
| 3I-F01 | Export Acquisition CSV respects report date filters + assigned scope |
| 3I-F02 | Columns match acquisition commercial export (no clinical narrative / private paths / funding reference) |
| 3I-F03 | Unauthorised user denied |

## G. Security

| ID | Expected |
| --- | --- |
| 3I-G01 | Admin / Manager can view |
| 3I-G02 | Care Coordinator per existing `jmrs_view_reports` |
| 3I-G03 | Assessor / Support Worker denied acquisition/commercial reports |

## H. Upgrade (DB 2.21.0 → 2.28.0)

| ID | Expected |
| --- | --- |
| 3I-H01 | Direct upgrade from released v1.2.0 DB `2.21.0` applies 2.22→2.28 in sequence |
| 3I-H02 | Existing referrals, homes, bedrooms, occupancies, care plans/teams/schedules/visits/MAR/documents preserved |
| 3I-H03 | No automatic remapping, fake stage history, fake Package Costs / LA decisions / commencement |
| 3I-H04 | No emails on migration |

## I. Fresh install

| ID | Expected |
| --- | --- |
| 3I-I01 | Activation creates current schema including Phase 3 tables/columns |
| 3I-I02 | Canonical stages unique; `jmrs_db_version` = `2.28.0` |
| 3I-I03 | Re-activation idempotent |

## J. Regression

Public Referral · Express Interest · Assessment Scheduling · Assessment Outcome Review · Package Cost · Package Cost Email · LA Decision · Not Proceeding · Transition Planning · Care Commencement · Pipeline Dashboard · Supported Living · Own Home · Care Plans · Care Teams · Schedules · Visits · MAR · Documents · existing Reports (SL/vacancy/visits).

---

## Dashboard vs Reports

| Surface | Answers |
| --- | --- |
| Pipeline Dashboard (3H) | What needs doing right now? |
| Acquisition Reports (3I) | What happened to referrals we received in the period? |

---

## Sign-off

| | |
| --- | --- |
| Phase 3I Acquisition Reporting | Pass / Fail |
| Full Phase 3 regression | Pass / Fail |
| Ready for release version bump | Yes / No (separate step) |
| Tester signature | |
| Date | |
