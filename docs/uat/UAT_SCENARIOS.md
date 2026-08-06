# UAT Scenarios — JM Referral Platform v1.1.0

Mandatory end-to-end scenarios. Execute with fictional data from `UAT_DATA_SETUP.md`.  
Map detailed steps to IDs in `UAT_TEST_CASES.md`.

**Mandatory for exit:** Scenarios 1–7 and 9–10.  
**Mandatory for reporting features:** Scenario 8 (if reports are in release scope — included for v1.1.0 admin surfaces).

---

## Scenario 1 — Routine family referral

**Goal:** Full happy path from public intake through portal clinical ops to archive/restore.

**Roles:** Public Referrer → Care Coordinator → Assessor (optional) → Support Worker A → Referral Manager

**Flow:**

1. Public wizard submit for Mary Brown (routine).  
2. Confirmation receipt shown; referral number recorded.  
3. Ops + referrer emails received (or visible in mail catcher).  
4. Referral appears in portal/admin list for Coordinator.  
5. Coordinator reviews and assigns.  
6. Assessment created/updated (portal preferred).  
7. Care plan created/updated.  
8. Care team: add John Testworker.  
9. Schedule created; visits generated for short window.  
10. Support Worker A executes a visit; tasks completed.  
11. Manager reviews completed visit.  
12. Archive referral; confirm read-only.  
13. Restore; confirm writable again.

**Pass if:** Each step succeeds without permission errors for the acting role; Worker B cannot access this client’s execute path if not assigned; no PHI in URLs.

---

## Scenario 2 — Urgent hospital referral

**Goal:** Urgent priority and operational alert handling.

**Roles:** Public/Admin intake → Referral Manager / Care Coordinator

**Flow:**

1. Submit as Dr Sarah Evans / Northside Medical Centre with **urgent** priority.  
2. Confirm urgent marking on referral.  
3. Operational alert visible to roles with `jmrs_view_operational_alerts`.  
4. Assign to Coordinator.  
5. Assessment + care plan completed.  
6. Upcoming visit present on dashboard/referral.  
7. Alert clears or no longer lists item after resolution conditions met (per product rules).

**Pass if:** Urgent path is visible to managers; Support Worker without alerts cap cannot open alerts admin; assignment works.

---

## Scenario 3 — Medication workflow

**Goal:** MAR Given + Refused and exception visibility.

**Roles:** Coordinator/Manager (manage meds) → Support Worker A (execute + administer) → Manager (review/alerts)

**Flow:**

1. Add Amlodipine Testmed (and optionally Paracetamol).  
2. Schedule/ensure visit for Mary Brown assigned to Worker A.  
3. Worker A executes visit; record administration **Given** (dose required).  
4. On another visit, record **Refused** (reason required).  
5. Confirm medication exception / alert behaviour for managers when applicable.  
6. Manager reviews completed visit(s).

**Pass if:** Validation enforces dose/reason rules; Worker B denied; inactive meds display correctly; no duplicate admin rows for same visit/med.

---

## Scenario 4 — Worker reassignment

**Goal:** Access follows assignment; no cross-worker leak.

**Roles:** Care Coordinator → Support Worker A → Support Worker B

**Flow:**

1. Assign Support Worker A to care team / visits.  
2. Create future visits for A.  
3. Reassign care-team member and/or visit assigned user to B.  
4. Confirm A loses access where AccessPolicy/ownership requires.  
5. Confirm B gains view/execute as expected.  
6. Attempt A execute on B’s visit → denied.  
7. Attempt B open A-only referral (if fully removed) → generic 404.

**Pass if:** No cross-worker visit execution; list scoping correct.

---

## Scenario 5 — Archive and restore

**Goal:** Archive enforcement.

**Roles:** Referral Manager (archive/restore) → Coordinator (attempt mutate) → Worker (view)

**Flow:**

1. Archive fictional referral with reason.  
2. Excluded from Active list; visible under Archived.  
3. Portal/admin show archived banner; mutation actions hidden.  
4. Forged POST (edit/assessment/visit) rejected.  
5. Restore.  
6. Normal edit/clinical actions return for permitted roles.

**Pass if:** No successful clinical write while archived; restore restores mutability.

---

## Scenario 6 — Documents

**Goal:** Private storage and authorization.

**Roles:** Public Referrer → Coordinator → Support Worker A (allowed) → Worker B or Assessor without access (denied)

**Flow:**

1. Public private upload (if enabled) on intake.  
2. Staff secure download via plugin URL succeeds for authorized user.  
3. Unauthorized user denied (403/404 per product).  
4. Staff upload from admin if applicable.  
5. Missing/corrupt file: safe failure message (no stack path leak).  
6. UI/network must not expose raw `jmrs-private` filesystem paths to browsers as open directories.

**Pass if:** Downloads gated; failures safe; no public Media URL for new private files.

---

## Scenario 7 — Permissions matrix

**Goal:** Every role checked against major actions.

**Roles:** All staff personas + public

For each role, exercise (portal and/or admin as appropriate):

- Referral view / edit  
- Assessment  
- Care plan / review  
- Medication manage / MAR  
- Care team  
- Scheduling / generate  
- Visit manage / execute  
- Manager review  
- Archive / restore  
- Reports / alerts  
- Settings  

**Pass if:** Results match `UAT_ROLE_MATRIX.md`. Record each cell via `UAT-SEC-*` / role cases.

---

## Scenario 8 — Reports and exports

**Goal:** Reporting integrity and CSV safety.

**Roles:** Referral Manager / Coordinator (with reports) → Assessor/Worker (denied)

**Flow:**

1. Generate test activity (referrals/visits).  
2. Confirm KPI/dashboard counts move as expected.  
3. Open reports; charts render with table fallback.  
4. Date filters apply.  
5. Full CSV + section CSV + referral CSV download.  
6. Confirm formula-injection escaping for cells starting with `=`, `+`, `-`, `@`.  
7. Print view usable.

**Pass if:** Authorized only; exports escape formulas; no fatal errors.

---

## Scenario 9 — Public form abuse controls

**Goal:** Spam and upload hardening.

**Roles:** Public (unauthenticated)

**Tests:**

- Honeypot filled → reject  
- Too-fast submission → reject  
- Invalid required values → validation errors  
- Rate limit after repeated posts  
- Oversized upload rejected  
- Invalid file type rejected  
- Duplicate refresh does not double-create (or is safely handled)  
- No PII in URL query string or world-readable logs  

**Pass if:** Abusive submissions fail closed; legitimate submit still works afterward.

---

## Scenario 10 — Failure and recovery

**Goal:** Graceful degradation and recoverability.

**Tests:**

- SMTP unavailable → submission still stores referral; user messaging acceptable; ops aware  
- Missing email template → no fatal; logged safely  
- Invalid portal route → branded 404  
- Expired/invalid receipt token → safe failure  
- Database validation error on form → errors shown; no partial corrupt row where product prevents it  
- Private file missing → safe download failure  
- Plugin deactivate / reactivate → data intact; roles remain  
- Upgrade migration on copy of DB → schema version advances  
- Backup restore drill → site + one referral + one private doc recoverable  

**Pass if:** No data corruption; no path/PII leaks; restore documented.

---

## Scenario completion tracker

| # | Scenario | Mandatory | Status | Tester | Date |
| --- | --- | --- | --- | --- | --- |
| 1 | Routine family referral | Yes | | | |
| 2 | Urgent hospital referral | Yes | | | |
| 3 | Medication workflow | Yes | | | |
| 4 | Worker reassignment | Yes | | | |
| 5 | Archive and restore | Yes | | | |
| 6 | Documents | Yes | | | |
| 7 | Permissions | Yes | | | |
| 8 | Reports and exports | Yes | | | |
| 9 | Public abuse controls | Yes | | | |
| 10 | Failure and recovery | Yes | | | |
