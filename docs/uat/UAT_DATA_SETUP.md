# UAT Data Setup — Fictional Seed Data Only

**Rule:** Never use real patient, staff personal, or live organisational data.  
All names, emails, phones, addresses, medications, and documents below are **synthetic**.

Store evidence under a private `uat-evidence/` folder (gitignored). Do **not** commit screenshots containing even fictional PHI to public remotes if your policy forbids it — prefer local/sharepoint storage.

---

## People

### Client

| Field | Value |
| --- | --- |
| Name | **Mary Brown** |
| Date of birth | 1952-03-14 |
| Address | 14 Test Lane, Exampletown, EX1 2AB |
| Phone | 01632 960001 |
| Email | mary.brown.uat@example.test |

### Referrer (family / routine)

| Field | Value |
| --- | --- |
| Name | **Alex Familyreferrer** |
| Relationship | Daughter |
| Organisation | — |
| Email | alex.family.uat@example.test |
| Phone | 07700 900001 |

### Referrer (hospital / urgent)

| Field | Value |
| --- | --- |
| Name | **Dr Sarah Evans** |
| Organisation | **Northside Medical Centre** |
| Email | sarah.evans.uat@example.test |
| Phone | 01632 960100 |
| Role | Discharge coordinator (fictional) |

### Support workers (WP users)

| Display name | Username suggestion | Email |
| --- | --- | --- |
| **John Testworker** | `uat.worker.a` | john.testworker@example.test |
| **Rebecca Testworker** | `uat.worker.b` | rebecca.testworker@example.test |

### Staff users

See `UAT_ROLE_MATRIX.md` for coordinator, manager, assessor, admins.

---

## Medications (fictional)

| Name | Strength | Dosage | Route | Frequency | Notes |
| --- | --- | --- | --- | --- | --- |
| Amlodipine Testmed | 5 mg | 1 tablet | Oral | Once daily | UAT only |
| Paracetamol Testmed | 500 mg | 1–2 tablets | Oral | As directed | UAT only |

Do not invent controlled-drug workflows beyond existing product fields.

---

## Documents (fictional)

Create tiny PDFs or text files named clearly, e.g.:

- `UAT-Mary-Brown-GP-letter.pdf`
- `UAT-Northside-discharge-summary.pdf`

Content must be obviously fake (header: “FICTIONAL UAT DOCUMENT — NOT A REAL MEDICAL RECORD”).

---

## Seed scenarios to prepare

### A. Routine family referral (Scenario 1)

1. Submit public wizard as Alex Familyreferrer for Mary Brown (routine priority).  
2. Confirm receipt + emails.  
3. Leave unassigned initially, then Care Coordinator assigns and progresses.  
4. Build assessment → care plan → care team (include John Testworker as primary or active).  
5. Create weekly schedule → generate a short date window (≤7 days).  
6. Keep at least one future visit for John to execute.

### B. Urgent hospital referral (Scenario 2)

1. Public or admin intake: Dr Sarah Evans / Northside Medical Centre.  
2. Set **urgent** priority and hospital-style referrer type if available.  
3. Confirm operational alert appears for managers/coordinators.  
4. Assign to Care Coordinator; complete assessment + care plan quickly.  
5. Ensure an upcoming visit exists.

### C. Referral with documents

1. On public form (if uploads enabled), attach `UAT-Mary-Brown-GP-letter.pdf`.  
2. Optionally add staff upload of discharge summary from admin.  
3. Record document IDs for SEC download tests.

### D. Referral with medication

1. On Mary Brown (or clone referral), add Amlodipine + Paracetamol Testmed.  
2. Use for Scenario 3 MAR Given / Refused.

### E. Referral with schedule and visits

1. Active weekly schedule Mon/Wed fictional times (e.g. 10:00–10:45).  
2. Generate visits for a 7-day window.  
3. Assign visits to John Testworker.

### F. Archived referral

1. Complete a spare fictional referral or clone.  
2. Archive with reason: `UAT archive test — fictional`.  
3. Keep ID for archive filter / mutate-block tests.

### G. Assigned to Support Worker A but not B

1. Care team: John Testworker active; Rebecca Testworker **not** assigned.  
2. Referral assignee / AccessPolicy scope such that A can view, B cannot.  
3. Visit `assigned_user_id` = John’s user ID.

---

## Environment checklist before seeding

- [ ] Staging only  
- [ ] Backup taken  
- [ ] Portal + public form enabled  
- [ ] SMTP or mail catcher ready  
- [ ] Test users created  
- [ ] Service types / workflow stages present  

---

## Cleanup after UAT (optional)

Archive or clearly mark all UAT referrals with prefix `UAT-` in notes. Do not delete production-like data. Prefer leaving seed data on staging for regression until the next wipe.

---

## No automated seeder (preferred)

This release prefers **manual** setup using the steps above.  
Do **not** add production seeder code unless JM explicitly requests an admin-only `WP_DEBUG` helper later.
