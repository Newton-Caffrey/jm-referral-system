# Administrator Guide — JM Referral System v1.0.0

Guide for WordPress Administrators and JM Administrators operating the system day to day.

---

## Roles overview

| Role | Typical use |
| --- | --- |
| WordPress Administrator | Full WP + full JM access |
| JM Administrator | Full JM clinical/ops (no WP site settings unless also WP Admin) |
| Referral Manager | Intake, assignment, archive, oversight |
| Care Coordinator | Care planning, team, schedules, visits |
| Assessor | Assessments and related referral work |
| Support Worker | Scoped to assigned clients/visits |

Access is enforced by **capabilities** and **AccessPolicy** (record-level), not role name alone.

<!-- Screenshot: Users → edit user → JM role -->

---

## Dashboard

**J&M Referrals → Dashboard**

Shows role-scoped widgets, for example:

- Active / new referral counts
- Recent referrals
- Upcoming visits
- Visits awaiting review (managers)
- Operational alert summary (when permitted)
- Medication exception counts (when permitted)

Quick links open the admin referral list or related screens. Support Workers see “my” scoped metrics.

---

## Referrals

### List

Search, status, priority, assignee (when permitted), archive scope, pagination (20/50/100), CSV export (capability-gated).

### Create / edit

Admin create and edit forms for client, referrer, service, priority, assignment, care requirements, and public-intake fields when present.

### View

Central clinical record: summary, notes, documents, assessment, care plan, care team, schedules, visits (with execution/review when permitted), medications/MAR, activity timeline.

Archived referrals are **read-only**; mutations are blocked server-side.

<!-- Screenshot: Referral View header -->

---

## Assessments

Structured domiciliary assessment on the referral: overview, daily living, communication, home/safety, support network, care package preferences, summary/recommendations.

Logged in the activity timeline.

---

## Care plans

Create/update/activate care plans linked to an assessment where required. Reviews and version history are available to permitted roles. Content fields cover visit pattern, task areas, risks, and goals.

---

## Care team

Assign staff with team role, primary flag, dates, and notes. Used for scheduling and visit assignment helpers.

---

## Scheduling

Create repeating or one-off schedules, assign team members, generate visits for a date window. Regeneration skips existing `generation_key` rows. Hard limits apply per request (see Known Limitations).

---

## Visits

- Manual create/edit where permitted
- Generated visits from schedules
- Field execution: arrival/departure, outcome, tasks, optional MAR
- Manager review of completed visits
- Pagination on Referral View visit list

---

## Medication

Maintain medication list (name, strength, dosage, route, frequency, status, dates). Administer during visit execution when capability and rules allow. Exception signals appear on dashboard/alerts for managers.

---

## Operational alerts

**J&M Referrals → Operational Alerts**

Rule-based operational signals (unassigned high priority, overdue reviews, visit exceptions, medication exceptions, etc.). Filter by severity/type. Dashboard reuses one calculation where possible.

---

## Reports

KPI summaries, tables, and optional charts for permitted roles. CSV export for sections/full report when allowed. Scoped for Support Workers where AccessPolicy applies.

---

## Settings

**J&M Referrals → Settings**

| Area | Purpose |
| --- | --- |
| Public Referral | Enable form, branding, spam-related settings, uploads |
| Staff Portal | Enable portal, branding, base path, optional wp-admin redirect |
| Private Document Migration | Batch copy legacy Media Library files to private storage |
| Data Integrity Check | Counts only — no automatic repair |
| Backup / uninstall notes | Operational reminders |

---

## Staff portal

Optional frontend app at `/staff-portal/` (configurable). Read-only foundation in v1.0: dashboard, referral list, referral view, secure downloads. Disabled by default.

Administrators normally keep using wp-admin. See `docs/STAFF_PORTAL.md` and `docs/STAFF_USER_GUIDE.md`.

---

## Public referral intake

Website shortcode `[jmrs_public_referral_form]`. Creates real referrals with `submission_channel` = website. Private uploads only. Ops + confirmation emails when mail works.

See `docs/PUBLIC_REFERRAL_GUIDE.md`.

---

## Archive and retention

- Archive / restore (capability-gated)
- Permanent delete only for empty referrals with no blocking dependents
- List filters: Active / Archived / All
- Policy: `docs/DATA_RETENTION_POLICY.md`

---

## Security practices for admins

- Prefer least-privilege JM roles for day-to-day staff
- Keep portal wp-admin redirect off until UAT passes
- Ensure HTTPS and strong passwords
- Back up DB + `jmrs-private` together
- Report vulnerabilities per `SECURITY.md` / `docs/SECURITY.md`

---

## Related documents

- `docs/INSTALLATION_GUIDE.md`
- `docs/STAFF_USER_GUIDE.md`
- `docs/TROUBLESHOOTING.md`
- `docs/FAQ.md`
