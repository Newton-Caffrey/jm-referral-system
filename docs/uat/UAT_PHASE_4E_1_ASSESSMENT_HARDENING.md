# UAT — Phase 4E.1 Assessment Hardening and Operational Metrics

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `f7b037be2834c0aafe4fc68693e5abe1b756f20b` (Phase 4D.1)

**Scope:** Terminal read-only completed assessments; derived Management Dashboard assessment metrics; no rebuild of the assessment module.

**Out of scope:** Reopen/correction workflow; Cancel Appointment; assessment emails; multi-assessment; schema/status column; package costing; new routes/capabilities.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Definitions

| Item | Rule |
| --- | --- |
| Completed assessment | `outcome` is not `pending` (`suitable`, `suitable_with_conditions`, or `not_suitable`) |
| Pending | Editable clinical + schedule/reschedule/needs-rescheduling under existing rules |
| Completed | Clinical and scheduling read-only; no reopen in this phase |
| Cardinality | One assessment row per referral |
| Dashboard scheduled | Pending + `scheduled_at` ≥ now; non-archived; scoped |
| Dashboard past scheduled | Pending + `scheduled_at` &lt; now; not labelled missed/failed |
| Dashboard completed | Non-pending outcomes; outcome distribution excludes pending |
| Assessor membership | Grants no referral or dashboard access |

---

## Activation and dashboard

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Assessment section displayed on Operations dashboard | **PASS** |
| Product remained 1.4.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |

---

## Test referral

| Item | Value |
| --- | --- |
| Staging referral ID | **11** |
| Initial assessment | Absent |
| Referral state | Active, non-archived |
| Use | Phase 4E.1 assessment UAT only |

User IDs / extra database IDs were **not captured** (do not invent).

---

## Assessment scheduling

| Check | Result |
| --- | --- |
| Assessment scheduling succeeded | **PASS** |
| Eligible Assessor selected | **PASS** |
| `assessment_scheduled` activity created once | **PASS** |
| Scheduled dashboard count increased by 1 | **PASS** |
| Assessment appeared in upcoming list | **PASS** |
| No assessment contact name on dashboard | **PASS** |
| No assessment telephone on dashboard | **PASS** |
| No assessment email on dashboard | **PASS** |
| No location address or scheduling notes on dashboard | **PASS** |

---

## Rescheduling

| Check | Result |
| --- | --- |
| Assessment rescheduling succeeded | **PASS** |
| `assessment_rescheduled` activity created once | **PASS** |
| Identical reschedule submission was a no-op | **PASS** |
| No unnecessary record update | **PASS** |
| No misleading activity created | **PASS** |

---

## Needs rescheduling

| Check | Result |
| --- | --- |
| Needs rescheduling action succeeded | **PASS** |
| `assessment_needs_rescheduling` activity created once | **PASS** |
| No email sent | **PASS** |

---

## Past-scheduled classification

| Check | Result |
| --- | --- |
| Pending assessment scheduled in the past classified correctly | **PASS** |
| Past-scheduled count increased by 1 | **PASS** |
| Appeared in Past scheduled assessments | **PASS** |
| Objective wording used | **PASS** |
| Not labelled missed, failed or overdue | **PASS** |

---

## Pending assessment

| Check | Result |
| --- | --- |
| Pending assessment remained editable | **PASS** |
| Clinical fields remained editable | **PASS** |
| Summary remained editable | **PASS** |
| Recommendations remained editable | **PASS** |
| Pending clinical save succeeded | **PASS** |
| Assessment remained pending after pending save | **PASS** |
| No premature completion activity | **PASS** |
| No premature pipeline transition | **PASS** |

---

## Archived referral

| Check | Result |
| --- | --- |
| Pending assessment remained readable after archive | **PASS** |
| Clinical Save controls unavailable | **PASS** |
| Scheduling and rescheduling controls unavailable | **PASS** |
| Direct mutation denied | **PASS** |
| Denied mutation created no assessment activity | **PASS** |
| Referral restored successfully | **PASS** |
| Pending assessment controls returned after restoration | **PASS** |

---

## Assessment completion

| Field | Value |
| --- | --- |
| Outcome | Suitable |
| Assessment date | 2026-08-27 |
| Summary | Phase 4E.1 focused assessment UAT completed. |
| Recommendations | Proceed to package costing. |

| Check | Result |
| --- | --- |
| Suitable completion succeeded | **PASS** |
| One `assessment_completed` activity created | **PASS** |
| No duplicate completion activity | **PASS** |
| Existing transition toward `package_cost_required` | **PASS** |
| No additional or unintended transition | **PASS** |
| No email sent | **PASS** |

---

## Dashboard after completion

| Check | Result |
| --- | --- |
| Completed-assessment count increased by 1 | **PASS** |
| Suitable outcome count increased by 1 | **PASS** |
| Stopped contributing to scheduled pending count | **PASS** |
| Stopped contributing to past-scheduled pending count | **PASS** |
| Outcome distribution contained no pending record | **PASS** |
| Dashboard remained privacy-safe | **PASS** |

Privacy omissions confirmed (not shown on dashboard): `assessment.uat@example.com`, `+44 20 7000 2000`, Assessment UAT Contact, assessment summary, recommendations, scheduling notes, clinical-domain narrative.

---

## Completed read-only state (Staff Portal)

| Check | Result |
| --- | --- |
| Completed assessment displayed read-only | **PASS** |
| Completed/read-only notice displayed | **PASS** |
| Normal Save Assessment form absent | **PASS** |
| Scheduling action absent | **PASS** |
| Rescheduling action absent | **PASS** |
| Needs rescheduling action absent | **PASS** |
| Assessment data remained readable | **PASS** |
| No editable clinical form in hidden markup | **PASS** |

---

## Direct completed-edit protection

Crafted attempt submitted: `outcome=not_suitable`, tampered summary, `workflow_stage_id=999999`, `status=cancelled`.

| Check | Result |
| --- | --- |
| Request denied | **PASS** |
| Outcome remained Suitable | **PASS** |
| Summary remained unchanged | **PASS** |
| Workflow stage remained unchanged | **PASS** |
| Referral status remained unchanged | **PASS** |
| Pipeline position remained unchanged | **PASS** |
| No assessment activity created | **PASS** |
| No second `assessment_completed` event | **PASS** |

---

## Access

| Check | Result |
| --- | --- |
| Assessor access followed existing referral scope | **PASS** |
| Assessor assignment granted no extra referral access | **PASS** |
| Assessor assignment granted no Management Dashboard access | **PASS** |
| Support Worker assessment mutation denied | **PASS** |
| Support Worker Management Dashboard access denied | **PASS** |
| No new WordPress capability added | **PASS** |

---

## Responsive (~375px)

| Check | Result |
| --- | --- |
| Completed read-only assessment fitted the viewport | **PASS** |
| Assessment dashboard section stacked correctly | **PASS** |
| Long Assessor names wrapped safely | **PASS** |
| Tables and lists remained usable | **PASS** |
| No page-level horizontal overflow | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| No emails | **PASS** |
| Referral responsibility values unchanged | **PASS** |
| Meeting data unchanged | **PASS** |
| Meeting attendees unchanged | **PASS** |
| Legacy `workflow_stage_id` unchanged except no approved change | **PASS** |
| Referral status unchanged except existing pipeline behaviour | **PASS** |
| Management Dashboard remained read-only | **PASS** |

---

## Items not manually exercised

| Item | Status | Reason |
| --- | --- | --- |
| Not-suitable completion on a second referral | **NOT RUN — CODE REVIEWED** | Focused UAT used one disposable referral (11) completed with Suitable. Existing `not_suitable` → `assessment_review_required` transition was not modified this phase; reviewed in code. |
| Admin-side completed-assessment presentation | **NOT RUN — CODE REVIEWED** | Staff Portal completed read-only path was manually checked. Admin read-only branch (`!can_edit_assessment` + completed notice) reviewed in code; shared completion policy blocks editable completed forms. |

These are **non-blocking**.

---

## Test-data cleanup (referral 11)

| Item | Result |
| --- | --- |
| Referral used | **11** |
| Permanent deletion via JMRS | **Not confirmed in the supplied UAT narrative** — do not invent |
| Archive-only residual | **Not confirmed** |
| Notes | Preferred cleanup is permanent delete of referral 11 and related assessment with dashboard totals restored. This checkpoint does **not** claim deletion or archive was performed. |

---

## Confirmations

- Product **1.4.0** · DB **2.29.0** · rewrite **1.2.7**  
- Assessment remains one-to-one; completed = non-pending outcome; terminal read-only  
- No reopen/correction; no separate appointment cancellation; no assessment emails  
- Assessor assignment grants no access; existing pipeline transitions remain  
- Dashboard assessment metrics derived; archived excluded; narrative/contacts excluded  
- Focused manual staging UAT **PASS** 2026-08-27  

| Tester | Context | Date | Result |
| --- | --- | --- | --- |
| Staging focused UAT | Phase 4E.1 assessment hardening | 2026-08-27 | **PASS** |
