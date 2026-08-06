# UAT Test Cases — JM Referral Platform v1.1.0

Copy rows into a spreadsheet if preferred. Leave **Actual / Pass-Fail / Notes / Evidence** blank until execution.

**Severity if failed:** Critical / High / Medium / Low (see `UAT_PLAN.md`).

---

## How to use each case

| Field | Meaning |
| --- | --- |
| Test ID | Stable ID |
| Scenario | Links to `UAT_SCENARIOS.md` |
| Role | Acting persona |
| Preconditions | Environment + data |
| Steps | Numbered actions |
| Expected | Observable outcome |
| Actual | Filled by tester |
| Pass/Fail | Pass / Fail / Blocked |
| Severity if failed | If Fail |
| Notes | Free text |
| Evidence | Path under `uat-evidence/` |

---

## Public intake (`UAT-PUB-*`)

### UAT-PUB-001
| | |
| --- | --- |
| Scenario | 1 |
| Role | Public Referrer |
| Preconditions | Public form enabled; SMTP or catcher ready |
| Steps | 1. Open public referral page. 2. Complete wizard for Mary Brown (routine). 3. Submit. |
| Expected | Success/receipt with referral reference; no PHP error. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-002
| | |
| --- | --- |
| Scenario | 1 |
| Role | Public Referrer |
| Preconditions | PUB-001 succeeded |
| Steps | 1. Note referral number from receipt. 2. Confirm confirmation email to referrer (if configured). |
| Expected | Email received or logged in catcher; body has reference without leaking other clients. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-003
| | |
| --- | --- |
| Scenario | 2 |
| Role | Public Referrer |
| Preconditions | Form enabled |
| Steps | 1. Submit urgent hospital referral as Dr Sarah Evans / Northside Medical Centre. |
| Expected | Referral created with urgent priority; receipt shown. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-004
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Form enabled |
| Steps | 1. Fill honeypot field (inspect DOM if hidden). 2. Submit otherwise valid form. |
| Expected | Submission rejected / not stored as valid referral. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-005
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Form enabled |
| Steps | 1. Submit valid form in under minimum timing threshold (too-fast). |
| Expected | Rejected as too fast. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-006
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Form enabled |
| Steps | 1. Omit required fields. 2. Submit. |
| Expected | Validation errors; no incomplete accepted referral. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-007
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Uploads enabled |
| Steps | 1. Attach oversized file beyond limit. 2. Submit. |
| Expected | Upload rejected with safe message. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-008
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Uploads enabled |
| Steps | 1. Attach disallowed file type (e.g. `.exe`). 2. Submit. |
| Expected | Rejected; no file stored in private dir. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-009
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Rate limit configured |
| Steps | 1. Submit repeatedly from same client beyond limit. |
| Expected | Later submissions blocked by rate limit. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-PUB-010
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | PUB-001 |
| Steps | 1. Refresh receipt/success page. 2. Inspect browser URL for client PII. |
| Expected | No duplicate create (or safe idempotent behaviour); URL has no client name/email/phone. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Referral / portal (`UAT-REF-*`)

### UAT-REF-001
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | PUB-001 referral exists; portal enabled |
| Steps | 1. Login. 2. Open portal dashboard. 3. Open Referrals list. 4. Find Mary Brown referral. |
| Expected | Referral listed; dashboard loads; breadcrumbs present. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REF-002
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | REF-001 |
| Steps | 1. Open referral view. 2. Confirm client summary header. 3. Edit referral (portal). 4. Save. |
| Expected | Update succeeds; success notice; data persisted. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REF-003
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | REF-002 |
| Steps | 1. Assign referral / set workflow stage as used operationally. |
| Expected | Assignment/stage saved; activity logged. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REF-004
| | |
| --- | --- |
| Scenario | 4 |
| Role | Support Worker B |
| Preconditions | Referral assigned only to Worker A |
| Steps | 1. Login as B. 2. Open My Referrals. 3. Request A’s referral URL directly. |
| Expected | Not in list; direct URL → generic 404. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REF-005
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | Assigned to Mary Brown |
| Steps | 1. Attempt portal Edit Referral. |
| Expected | Edit control absent or 403/404 on forced URL. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REF-006
| | |
| --- | --- |
| Scenario | 2 |
| Role | Referral Manager |
| Preconditions | Urgent hospital referral |
| Steps | 1. Open referral. 2. Confirm urgent priority badge/field. 3. Assign Coordinator. |
| Expected | Urgent visible; assignment succeeds. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Assessment (`UAT-ASM-*`)

### UAT-ASM-001
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | Editable referral |
| Steps | 1. Portal → Create/Edit Assessment. 2. Complete required fields. 3. Save. |
| Expected | Saved; notice; view shows assessment summary. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-ASM-002
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | Assigned referral |
| Steps | 1. Attempt assessment edit URL. |
| Expected | Denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-ASM-003
| | |
| --- | --- |
| Scenario | 1 |
| Role | Assessor |
| Preconditions | Referral accessible |
| Steps | 1. Update assessment via portal or admin. |
| Expected | Save succeeds for Assessor. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Care plan (`UAT-CP-*`)

### UAT-CP-001
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | Assessment exists (preferred) |
| Steps | 1. Create/generate care plan. 2. Save active fields. |
| Expected | Care plan saved; visible on referral view. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-CP-002
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | Care plan exists |
| Steps | 1. Portal care-plan review. 2. Submit outcome + next review date. |
| Expected | Review recorded; history updates; status/next review effects applied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-CP-003
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | Care plan exists |
| Steps | 1. View care plan. 2. Attempt review URL. |
| Expected | View OK; review denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Medication (`UAT-MED-*`)

### UAT-MED-001
| | |
| --- | --- |
| Scenario | 3 |
| Role | Care Coordinator |
| Preconditions | Mutable referral |
| Steps | 1. Add Amlodipine Testmed. 2. Save. |
| Expected | Medication listed active. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-002
| | |
| --- | --- |
| Scenario | 3 |
| Role | Care Coordinator |
| Preconditions | MED-001 |
| Steps | 1. Edit medication. 2. Set status paused/discontinued. |
| Expected | Status updates; inactive display behaviour correct. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-003
| | |
| --- | --- |
| Scenario | 3 |
| Role | Support Worker A |
| Preconditions | Visit assigned to A; active med |
| Steps | 1. Execute visit. 2. Record Given with dose. 3. Complete visit. |
| Expected | MAR saved; visit completed. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-004
| | |
| --- | --- |
| Scenario | 3 |
| Role | Support Worker A |
| Preconditions | Second visit |
| Steps | 1. Execute. 2. Record Refused with reason. |
| Expected | Validation requires reason; row saved. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-005
| | |
| --- | --- |
| Scenario | 3 |
| Role | Support Worker A |
| Preconditions | Active meds; execute form |
| Steps | 1. Submit Given without dose. |
| Expected | Validation error; not completed. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-006
| | |
| --- | --- |
| Scenario | 3 |
| Role | Referral Manager |
| Preconditions | Refused/exception recorded |
| Steps | 1. Check medication exception / alerts surfaces. |
| Expected | Exception visible to permitted roles only. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-MED-007
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | Assigned |
| Steps | 1. Attempt Add Medication URL. |
| Expected | Denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Visits / schedules (`UAT-VIS-*`)

### UAT-VIS-001
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | Care team includes Worker A |
| Steps | 1. Add care team member John Testworker. 2. Set primary if required. |
| Expected | Member saved; only one primary. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-002
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | VIS-001 |
| Steps | 1. Create weekly schedule. 2. Select weekdays. 3. Save. |
| Expected | Schedule saved; weekdays persist. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-003
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | Active schedule |
| Steps | 1. Generate visits for ≤7 days. 2. Confirm generation. 3. Re-run same window. |
| Expected | Visits created; second run skips duplicates. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-004
| | |
| --- | --- |
| Scenario | 1 |
| Role | Care Coordinator |
| Preconditions | — |
| Steps | 1. Manually schedule a visit for Worker A. |
| Expected | Visit saved with correct assignee. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-005
| | |
| --- | --- |
| Scenario | 1 |
| Role | Support Worker A |
| Preconditions | Own scheduled visit |
| Steps | 1. Portal Execute. 2. Arrival/departure/outcome. 3. Complete tasks. 4. Submit. |
| Expected | Visit completed; tasks stored; cannot execute again. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-006
| | |
| --- | --- |
| Scenario | 4 |
| Role | Support Worker B |
| Preconditions | Visit owned by A |
| Steps | 1. Open A’s execute URL. |
| Expected | 403/404; cannot complete. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-007
| | |
| --- | --- |
| Scenario | 1 |
| Role | Referral Manager |
| Preconditions | Completed unreviewed visit |
| Steps | 1. Portal Review Visit. 2. Add manager notes. 3. Submit. |
| Expected | Reviewed; awaiting-review count decreases. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-008
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | Completed visit |
| Steps | 1. Attempt manager review URL. |
| Expected | Denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-009
| | |
| --- | --- |
| Scenario | 4 |
| Role | Care Coordinator |
| Preconditions | Visits assigned to A |
| Steps | 1. Reassign visit/team to Rebecca Testworker. 2. Verify A/B access. |
| Expected | B can execute; A cannot on reassigned visit. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-VIS-010
| | |
| --- | --- |
| Scenario | 2 |
| Role | Care Coordinator |
| Preconditions | Urgent referral |
| Steps | 1. Ensure upcoming visit. 2. Confirm dashboard “Today’s Schedule” / upcoming list shows it for permitted users. |
| Expected | Visit visible; links stay on portal. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Archive (`UAT-ARC-*`)

### UAT-ARC-001
| | |
| --- | --- |
| Scenario | 5 |
| Role | Referral Manager |
| Preconditions | Mutable referral |
| Steps | 1. Archive with reason. |
| Expected | Success; archived badge; Active list excludes. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-ARC-002
| | |
| --- | --- |
| Scenario | 5 |
| Role | Care Coordinator |
| Preconditions | Archived referral |
| Steps | 1. Confirm mutation actions hidden. 2. Forge POST to edit/assessment if possible. |
| Expected | Writes rejected; read summaries OK. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-ARC-003
| | |
| --- | --- |
| Scenario | 5 |
| Role | Referral Manager |
| Preconditions | ARC-001 |
| Steps | 1. Filter Archived. 2. Restore. 3. Edit referral. |
| Expected | Restore OK; edit works. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-ARC-004
| | |
| --- | --- |
| Scenario | 7 |
| Role | Care Coordinator |
| Preconditions | Mutable referral |
| Steps | 1. Attempt archive. |
| Expected | Archive not available / denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Security / permissions (`UAT-SEC-*`)

### UAT-SEC-001
| | |
| --- | --- |
| Scenario | 7 |
| Role | Assessor |
| Preconditions | Assessor user |
| Steps | 1. Confirm no Settings access. 2. Confirm no visit execute. 3. Confirm reports/alerts denied. |
| Expected | Matches role matrix. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-002
| | |
| --- | --- |
| Scenario | 7 |
| Role | Support Worker A |
| Preconditions | — |
| Steps | 1. Confirm menus: Dashboard + My Referrals only. 2. Spot-check forbidden clinical manage URLs. |
| Expected | Forbidden actions denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-003
| | |
| --- | --- |
| Scenario | 6 |
| Role | Unauthorized staff |
| Preconditions | Document on Mary Brown; user without access |
| Steps | 1. Use secure download URL as unauthorized user. |
| Expected | Denied; no file bytes. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-004
| | |
| --- | --- |
| Scenario | 6 |
| Role | Care Coordinator |
| Preconditions | Private document exists |
| Steps | 1. Download via plugin link. 2. Confirm storage is under private path pattern (server-side), not public uploads URL. |
| Expected | Download works; no public direct file listing. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-005
| | |
| --- | --- |
| Scenario | 6 |
| Role | Care Coordinator |
| Preconditions | Delete/rename private file on disk (staging only) |
| Steps | 1. Attempt download. |
| Expected | Safe error; no filesystem path disclosure to user. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-006
| | |
| --- | --- |
| Scenario | 7 |
| Role | Referral Manager |
| Preconditions | — |
| Steps | 1. Confirm no JM Settings / service types / stages admin. |
| Expected | Settings menus absent or denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-007
| | |
| --- | --- |
| Scenario | 7 |
| Role | JM Administrator |
| Preconditions | — |
| Steps | 1. Open Settings. 2. View alerts/reports. |
| Expected | Allowed. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-008
| | |
| --- | --- |
| Scenario | 9 |
| Role | Public Referrer |
| Preconditions | Staging logs available |
| Steps | 1. Submit referral. 2. Scan recent logs for raw email/phone of client. |
| Expected | No unnecessary PII in logs; no stack traces with PHI. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-009
| | |
| --- | --- |
| Scenario | 10 |
| Role | Anyone |
| Preconditions | Portal enabled |
| Steps | 1. Open `/staff-portal/not-a-real-route/`. |
| Expected | Branded 404; no theme break; no data leak. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-SEC-010
| | |
| --- | --- |
| Scenario | 7 |
| Role | Unauthenticated |
| Preconditions | Portal enabled |
| Steps | 1. Open portal dashboard URL. |
| Expected | Redirect to login with return URL. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Reports (`UAT-REP-*`)

### UAT-REP-001
| | |
| --- | --- |
| Scenario | 8 |
| Role | Referral Manager |
| Preconditions | Activity exists |
| Steps | 1. Open Reports. 2. Confirm KPIs/charts/tables. |
| Expected | Page loads; charts or table fallback. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REP-002
| | |
| --- | --- |
| Scenario | 8 |
| Role | Referral Manager |
| Preconditions | REP-001 |
| Steps | 1. Apply date filters. 2. Export full CSV. 3. Export section CSV. 4. Export referral CSV. |
| Expected | Files download; filters affect results. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REP-003
| | |
| --- | --- |
| Scenario | 8 |
| Role | Referral Manager |
| Preconditions | Create referral/note with formula-like text `=1+1` where fields allow |
| Steps | 1. Export CSV. 2. Open in editor. |
| Expected | Formula cells escaped (e.g. leading `'`). |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REP-004
| | |
| --- | --- |
| Scenario | 8 |
| Role | Support Worker A |
| Preconditions | — |
| Steps | 1. Open reports admin URL. |
| Expected | Denied. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REP-005
| | |
| --- | --- |
| Scenario | 8 |
| Role | Referral Manager |
| Preconditions | — |
| Steps | 1. Open print view if available. |
| Expected | Printable layout without fatal errors. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-REP-006
| | |
| --- | --- |
| Scenario | 2 |
| Role | Referral Manager |
| Preconditions | Urgent referral |
| Steps | 1. Open Operational Alerts. |
| Expected | Alert listed for urgent/unassigned conditions per rules. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Backup / recovery (`UAT-BKP-*`)

### UAT-BKP-001
| | |
| --- | --- |
| Scenario | 10 |
| Role | WordPress Administrator |
| Preconditions | Staging |
| Steps | 1. Take DB + uploads backup including `jmrs-private`. 2. Record location in private evidence notes (not Git). |
| Expected | Backup completes. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-BKP-002
| | |
| --- | --- |
| Scenario | 10 |
| Role | WordPress Administrator |
| Preconditions | BKP-001; disposable staging clone preferred |
| Steps | 1. Deactivate plugin. 2. Reactivate. 3. Open a known referral + download a private doc. |
| Expected | Data intact; download works. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-BKP-003
| | |
| --- | --- |
| Scenario | 10 |
| Role | WordPress Administrator |
| Preconditions | Backup available |
| Steps | 1. On a clone, restore backup. 2. Verify referral number from PUB-001 and one document. |
| Expected | Restored successfully. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-BKP-004
| | |
| --- | --- |
| Scenario | 10 |
| Role | WordPress Administrator |
| Preconditions | Older DB copy if available |
| Steps | 1. Deploy build. 2. Load admin to trigger migrations. 3. Check `jmrs_db_version`. |
| Expected | Migrates cleanly; referrals remain. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

### UAT-BKP-005
| | |
| --- | --- |
| Scenario | 10 |
| Role | WordPress Administrator |
| Preconditions | Can break SMTP temporarily |
| Steps | 1. Disable SMTP. 2. Submit public referral. 3. Confirm referral stored. |
| Expected | Referral saved; mail failure handled without fatal; user messaging acceptable. |
| Actual | |
| Pass/Fail | |
| Severity if failed | |
| Notes | |
| Evidence | |

---

## Case count summary

| Prefix | Count | Focus |
| --- | --- | --- |
| UAT-PUB | 10 | Public intake & abuse |
| UAT-REF | 6 | Portal referral |
| UAT-ASM | 3 | Assessment |
| UAT-CP | 3 | Care plan / review |
| UAT-MED | 7 | Medications / MAR |
| UAT-VIS | 10 | Team / schedule / visits / review |
| UAT-ARC | 4 | Archive / restore |
| UAT-SEC | 10 | Permissions / documents / portal auth |
| UAT-REP | 6 | Reports / alerts / CSV |
| UAT-BKP | 5 | Backup / recovery / SMTP |
| **Total** | **64** | |

Add extra cases during UAT if gaps appear; allocate new IDs in the same prefix series (e.g. `UAT-VIS-011`).
