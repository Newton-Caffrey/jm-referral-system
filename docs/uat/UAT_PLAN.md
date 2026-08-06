# UAT Plan — JM Healthcare Referral Platform v1.1.0

**Product:** JM Referral System (WordPress plugin)  
**Target release:** `1.1.0`  
**Document owner:** Project delivery  
**Audience:** Testers, JM operational leads, developers  

This plan governs User Acceptance Testing (UAT) for the v1.1.0 Staff Portal and related clinical/public workflows already delivered in Phases 1.1A–1.1D. It does **not** introduce product features or change business rules.

---

## Purpose

Confirm that JM Healthcare staff and public referrers can complete agreed end-to-end workflows safely on a staging environment, with correct permissions, no PHI leakage, and acceptable operational behaviour, before production promotion.

---

## Scope

In scope for UAT:

- Public referral intake (wizard, spam controls, receipt, emails when SMTP works)
- Staff portal: login, dashboard, referral list/view/edit, assessment, care plan, care-plan review, medications, care team, schedules, visit create/edit/execute (tasks + MAR), manager visit review, archive/restore
- WordPress admin equivalents for regression of core clinical workflows
- Role and AccessPolicy boundaries (including Support Worker scoping)
- Documents (private upload/download, unauthorized denial)
- Operational alerts and reports/exports (admin)
- Backup / restore verification of DB + `jmrs-private`
- Failure and recovery smoke items listed in scenarios

---

## Out of scope

- New clinical fields or workflows not already shipped
- Referrer / family portals, REST API, mobile app
- Portal reports page / portal operational-alerts page (not yet built — test admin surfaces only)
- CAPTCHA products, timed retention purge, automated PHPUnit suite
- Legal/regulatory certification or “compliance sign-off” as a legal claim
- Production data or real patient records
- Performance/load testing beyond normal interactive use
- Theme / hosting customisation outside the plugin

---

## Test environment

| Item | Requirement |
| --- | --- |
| Host | Dedicated **staging** WordPress site (not production) |
| PHP | 8.0+ (8.1+ preferred) |
| WordPress | 6.0+ |
| HTTPS | Required on staging |
| SMTP | Configured for outbound mail (or documented mail catcher) |
| Plugin | Build under test with `vendor/` present |
| Schema | Migrations complete (`jmrs_db_version` matches build) |
| Portal | Staff portal **enabled**; base path documented |
| Public form | Public referral form **enabled** on a test page |
| Debug | `WP_DEBUG` / logging **on staging only** — never leave debug on production |
| Evidence | Private `uat-evidence/` folder (not committed) |

---

## Required roles

Create dedicated **fictional** WordPress users (see `UAT_ROLE_MATRIX.md` and `UAT_DATA_SETUP.md`):

| Persona | WP role / JM role |
| --- | --- |
| Public Referrer | Unauthenticated visitor (no WP account) |
| WordPress Administrator | `administrator` |
| JM Administrator | `jmrs_administrator` |
| Referral Manager | `jmrs_referral_manager` |
| Care Coordinator | `jmrs_care_coordinator` |
| Assessor | `jmrs_assessor` |
| Support Worker A | `jmrs_support_worker` |
| Support Worker B | `jmrs_support_worker` |

Do **not** share production credentials. Do **not** document real passwords in this repository.

---

## Entry criteria

UAT may start only when all of the following are true:

- [ ] Latest agreed code build deployed to staging
- [ ] Database migrations complete and verified
- [ ] SMTP / mail delivery verified (or mail catcher confirmed)
- [ ] Public referral page enabled and shortcode reachable
- [ ] Staff portal enabled; rewrites flushed; portal URL documented
- [ ] All UAT test users created per role matrix
- [ ] Full backup completed (database + `uploads/` including `jmrs-private/`)
- [ ] Debug / diagnostic logging enabled **on staging only**
- [ ] Fictional seed data prepared per `UAT_DATA_SETUP.md` (or ready to create during Scenario 1)
- [ ] Testers have access to `docs/uat/` and an evidence folder
- [ ] Known limitations reviewed (`docs/KNOWN_LIMITATIONS.md`)

---

## Exit criteria

UAT may close and production recommendation recorded only when:

- [ ] No unresolved **Critical** defects
- [ ] No unresolved **High** defects affecting core workflows (public intake, portal clinical day-to-day, permissions, documents, archive)
- [ ] All **mandatory** scenarios in `UAT_SCENARIOS.md` marked Pass (or waived in writing with JM owner acceptance)
- [ ] Security and permission tests (Scenario 7 + SEC cases) passed
- [ ] Backup and restore verified on staging
- [ ] Smoke checklist completed
- [ ] JM sign-off recorded in `UAT_SIGN_OFF.md`

---

## Severity levels

| Level | Definition |
| --- | --- |
| **Critical** | Data exposure, data corruption, total workflow failure, or security bypass |
| **High** | Core workflow blocked with no practical workaround |
| **Medium** | Workflow issue with a practical workaround |
| **Low** | Cosmetic, wording, or minor usability issue |

---

## Pass / fail rules

- A **test case** Passes when actual result matches expected result and no security/permission leak is observed.
- A **scenario** Passes when all of its mandatory test cases Pass (or failed only Low items explicitly accepted).
- Any Critical or High failure blocks exit until fixed and retested, unless JM owner formally accepts residual risk in sign-off (not permitted for Critical security/data defects).
- Flaky environment issues (SMTP sandbox, hosting timeouts) must be re-run once; persistent environment blockers are logged as Medium/High with environment notes — not as product Pass.

---

## Defect handling

1. Log in `UAT_DEFECT_LOG.md` (or linked tracker) with Test Case ID, severity, steps, evidence.
2. Developer reproduces on staging; fix on a branch; note fix commit.
3. Retest the failed case + nearest regression cases.
4. Close only when Retest = Pass.

Do not paste PHI, real emails of patients, or private file paths into Git-tracked logs.

---

## Sign-off process

1. Tester completes scenarios and smoke checklist.  
2. Defect log reviewed; exit criteria checked.  
3. `UAT_SIGN_OFF.md` completed by Developer, JM Project Owner, JM Operational Representative, and Data Protection / Compliance Representative where applicable.  
4. Recommendation chosen: Approved / Approved with accepted limitations / Not approved.  
5. Production promotion only after **Approved** or **Approved with accepted limitations** and a fresh backup plan.

This process does **not** assert legal or regulatory compliance.

---

## Rollback approach

If UAT or early production reveals Critical/High issues:

1. Stop further promotion.  
2. Restore the pre-change database backup and `uploads/` (including `jmrs-private/`).  
3. Redeploy previous known-good plugin ZIP.  
4. Flush permalinks / portal rewrites if URLs break.  
5. Verify login, one referral view, one document download, SMTP smoke.  
6. Document incident in defect log and schedule re-UAT.

---

## Related documents

| Document | Role |
| --- | --- |
| `UAT_ROLE_MATRIX.md` | Role expectations |
| `UAT_DATA_SETUP.md` | Fictional seed data |
| `UAT_SCENARIOS.md` | End-to-end scenarios |
| `UAT_TEST_CASES.md` | Structured cases |
| `UAT_DEFECT_LOG.md` | Defect register |
| `UAT_SIGN_OFF.md` | Formal sign-off |
| `docs/RELEASE_CHECKLIST.md` | Release engineering checklist |
| `docs/KNOWN_LIMITATIONS.md` | Accepted product limits |

---

## Final smoke checklist (compact)

Run after full UAT or before production cutover:

- [ ] Plugin activation / no fatal errors  
- [ ] Staff login → portal dashboard  
- [ ] Public referral submit → receipt  
- [ ] Portal referral editing  
- [ ] Assessment save  
- [ ] Care plan save  
- [ ] Medication add/edit  
- [ ] Schedule + generate visits  
- [ ] Visit execute (tasks)  
- [ ] MAR record  
- [ ] Manager visit review  
- [ ] Reports / CSV (admin)  
- [ ] Archive + restore  
- [ ] Backup present and restore drill noted  

See also Scenario 10 and `UAT-BKP-*` cases.
