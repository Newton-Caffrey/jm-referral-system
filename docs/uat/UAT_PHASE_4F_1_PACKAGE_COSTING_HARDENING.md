# UAT — Phase 4F.1 Package Costing Hardening and Operational Metrics

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `caa771300a7ec806f1318bee8281656c012c6271` (Phase 4E.1)

**Scope:** Harden existing Package Cost prepare/send milestone; sent terminal read-only; amount normalisation; no-op prepare; Operations Package Costing metrics. Do **not** rebuild Package Costing.

**Out of scope:** Component calculator; VAT; multi-currency UI; revision/withdrawal; auto-PDF; new package emails; LA-decision changes; placement transition; schema/routes/capabilities; Assessor amount-visibility product change.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Definitions

| Item | Rule |
| --- | --- |
| Current package | Latest `jmrs_referral_package_costs` row for the referral (`MAX(id)`) |
| Prepared | `status = prepared` |
| Sent (terminal) | `status = sent` — read-only; no reopen/revision in this phase |
| Package total | Optional manual GBP DECIMAL(12,2); blank allowed; zero allowed |
| Document | One referral-scoped PDF/DOC/DOCX linked via `document_id` |
| Send advances pipeline | Successful send only → `awaiting_la_decision` |
| Prepare does not advance | Prepare/update leaves pipeline unchanged |
| Dashboard Package Cost Required | Non-archived referrals on pipeline `package_cost_required` |
| Dashboard Prepared / Sent | Latest package-cost row status; non-archived; scoped |
| Dashboard Awaiting LA | Non-archived referrals on pipeline `awaiting_la_decision` |
| Operations privacy | No package total, recipient, submission reference, filename, or client details |
| Assessor panel amounts | **EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION** (view only; no prepare/send) |

---

## Activation

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Package Costing panel opened | **PASS** |
| Operations Package Costing section opened | **PASS** |
| Product remained 1.4.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |

---

## Test referral

| Item | Value |
| --- | --- |
| Staging referral ID | **10** |
| Initial pipeline stage | `package_cost_required` |
| Referral state | Active, non-archived during testing |
| Prior sent package | None before UAT |
| Assessment / meetings / responsibilities | Remained unchanged |

User IDs, package IDs, and document IDs were **not captured** (do not invent).

---

## Prepare with blank total

| Check | Result |
| --- | --- |
| Package prepared with blank `package_total` | **PASS** |
| Blank total accepted | **PASS** |
| Approved staging document linked | **PASS** |
| Linked document belonged to referral 10 | **PASS** |
| Currency remained GBP | **PASS** |
| One `package_cost_prepared` activity created | **PASS** |
| Preparing did not advance the acquisition pipeline | **PASS** |
| Prepared Packages dashboard count increased by 1 | **PASS** |
| No email sent | **PASS** |

---

## Valid total update

Submitted value: `£1,234.50`

| Check | Result |
| --- | --- |
| Amount normalised successfully | **PASS** |
| Displayed package total became £1,234.50 | **PASS** |
| Storage remained decimal-string based (not floating-point calculation) | **PASS** |
| One `package_cost_updated` activity created | **PASS** |
| Activity description contained no package amount | **PASS** |
| Pipeline remained `package_cost_required` | **PASS** |

---

## No-op update

| Check | Result |
| --- | --- |
| Same total and same document submitted again | **PASS** |
| Safe no-change behaviour displayed | **PASS** |
| No unnecessary update occurred | **PASS** |
| No `package_cost_updated` event created | **PASS** |
| Refresh created no duplicate event | **PASS** |

---

## Invalid amounts

| Check | Result |
| --- | --- |
| Negative amount rejected | **PASS** |
| Excess decimal precision rejected | **PASS** |
| Scientific notation rejected | **PASS** |
| Markup/script value rejected safely | **PASS** |
| Existing £1,234.50 value remained unchanged | **PASS** |
| Failed validations created no activity | **PASS** |
| Failed validations caused no pipeline transition | **PASS** |
| Raw malicious input was not rendered unsafely | **PASS** |

---

## Mass-assignment and tampering

Crafted values attempted: `currency = USD`, `status = sent`, `referral_id = 999999`, `sent_by = 1`, `workflow_stage_id = 999999`.

| Check | Result |
| --- | --- |
| Currency tampering blocked | **PASS** |
| Currency remained GBP | **PASS** |
| Package-status tampering blocked | **PASS** |
| Package remained Prepared before the valid send operation | **PASS** |
| `referral_id` tampering blocked | **PASS** |
| Package remained associated with referral 10 | **PASS** |
| Actor tampering blocked | **PASS** |
| Workflow-stage tampering blocked | **PASS** |
| No misleading activity created | **PASS** |

---

## Access — Assessor

| Check | Result |
| --- | --- |
| Package information remained readable under existing product policy | **PASS** |
| Prepare controls hidden | **PASS** |
| Send controls hidden | **PASS** |
| Direct mutation denied | **PASS** |
| Assessor received no new package permission | **PASS** |
| Assessor received no Management Dashboard access | **PASS** |

**EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION:** Assessor visibility of package amount remains an existing product behaviour that still requires future JM confirmation.

---

## Access — Support Worker

| Check | Result |
| --- | --- |
| Package-costing access denied | **PASS** |
| Package document access denied | **PASS** |
| Management Dashboard access denied | **PASS** |
| Responsibility or attendee membership granted no package access | **PASS** |

---

## Archived referral

| Check | Result |
| --- | --- |
| Prepared package remained readable to authorised commercial user | **PASS** |
| Update controls hidden | **PASS** |
| Send controls hidden | **PASS** |
| Direct prepare mutation denied | **PASS** |
| Direct send mutation denied | **PASS** |
| Denied attempts created no activity | **PASS** |
| Denied attempts sent no email | **PASS** |
| Referral restored successfully before send testing | **PASS** |

---

## Secure-portal send

Submitted: `send_method = secure_portal`, recipient `Test Local Authority Portal`, submission reference `PHASE-4F1-UAT-001`.

| Check | Result |
| --- | --- |
| Secure-portal send succeeded | **PASS** |
| `wp_mail` was not called | **PASS** |
| Package status became Sent | **PASS** |
| One `package_cost_sent` activity created | **PASS** |
| Pipeline advanced exactly once to `awaiting_la_decision` | **PASS** |
| Package amount absent from activity description | **PASS** |
| Recipient details absent from activity description | **PASS** |
| Document path and filename absent from activity description | **PASS** |

---

## Dashboard after send

| Check | Result |
| --- | --- |
| Package Cost Required count decreased by 1 | **PASS** |
| Prepared Packages count decreased by 1 | **PASS** |
| Sent Packages count increased by 1 | **PASS** |
| Awaiting LA Decision count increased by 1 | **PASS** |
| Dashboard used the latest package row only | **PASS** |
| Older package rows were not double-counted | **PASS** |
| Repeated refresh created no duplicate activity | **PASS** |
| Repeated refresh caused no second pipeline transition | **PASS** |

---

## Sent terminal state

| Check | Result |
| --- | --- |
| Sent/read-only notice displayed | **PASS** |
| Package metadata remained readable | **PASS** |
| Total input absent | **PASS** |
| Document replacement control absent | **PASS** |
| Prepare form absent | **PASS** |
| Send form absent | **PASS** |
| No editable hidden package form exposed | **PASS** |

---

## Direct sent-edit protection

Crafted values attempted: `package_total = 999999.99`, `currency = USD`, `status = prepared`, alternate `document_id`, `pipeline_stage = package_cost_required`.

| Check | Result |
| --- | --- |
| Direct sent-package mutation denied | **PASS** |
| Package total remained £1,234.50 | **PASS** |
| Currency remained GBP | **PASS** |
| Status remained Sent | **PASS** |
| Linked document remained unchanged | **PASS** |
| Pipeline remained `awaiting_la_decision` | **PASS** |
| No activity created | **PASS** |
| No email sent | **PASS** |

---

## Privacy

Operations Package Costing section did **not** display: £1,234.50; Test Local Authority Portal; PHASE-4F1-UAT-001; document filename; document path; client contact information; assessment narrative.

| Check | Result |
| --- | --- |
| Dashboard commercial privacy | **PASS** |
| Activity privacy | **PASS** |
| No package amount in activity descriptions | **PASS** |
| No recipient data in activity descriptions | **PASS** |

---

## Responsive design (~375px)

| Check | Result |
| --- | --- |
| Package Costing cards stacked correctly | **PASS** |
| Dashboard lists remained usable | **PASS** |
| Referral references wrapped safely | **PASS** |
| Sent package panel remained usable | **PASS** |
| No page-level horizontal overflow | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| Assessment data unchanged | **PASS** |
| Assessment outcome unchanged | **PASS** |
| Meeting records unchanged | **PASS** |
| Meeting attendees unchanged | **PASS** |
| `assigned_to` unchanged | **PASS** |
| `champion_user_id` unchanged | **PASS** |
| `transition_lead_user_id` unchanged | **PASS** |
| No unintended `workflow_stage_id` mutation | **PASS** |
| No LA decision was created | **PASS** |

---

## Email send workflow

**NOT RUN — CODE REVIEWED**

Reason: focused UAT used the `secure_portal` method to avoid sending a package to a real external recipient.

Code review confirmation:

| Item | Result |
| --- | --- |
| Existing `notify_package_cost_sent()` path retained | Confirmed |
| Linked validated package document is used | Confirmed |
| Email body excludes `package_total` | Confirmed |
| Failed email does not mark the package Sent | Confirmed |
| Failed email leaves the package Prepared | Confirmed |
| `package_cost_email_failed` is recorded truthfully | Confirmed |
| No new package email type was introduced | Confirmed |

Do not fabricate a manual PASS for live email send.

---

## Final test-referral state

| Item | Value |
| --- | --- |
| Referral 10 | **Retained** as a legitimate staging record |
| Package | Remains **Sent** |
| Pipeline | Remains `awaiting_la_decision` |
| Dashboard impact | Intentionally retained |
| Permanent deletion | Not claimed; package/document not deleted via SQL |

---

## Sign-off

| Item | Value |
| --- | --- |
| Staging referral ID | **10** |
| Tester | Staging focused UAT |
| Date | **2026-08-27** |
| Overall | **PASS** |
| Notes | Email send **NOT RUN — CODE REVIEWED**. Assessor package amount visibility still requires future JM confirmation. |
