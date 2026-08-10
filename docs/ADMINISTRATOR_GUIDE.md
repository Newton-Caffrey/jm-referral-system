# Administrator Guide — JM Referral System v1.3.0

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

**J&M Referrals → Reports** (`jmrs_view_reports`)

KPI summaries, tables, and Chart.js charts for permitted roles. CSV export for sections/full report when allowed.

### Supported Living — Current Snapshot (Phase 2G.1)

Aggregate estate and care-delivery metrics as of today. **Not** filtered by the report date range (changing This Month → This Year does not change occupancy).

| Area | Metrics |
| --- | --- |
| Estate | Active Homes, Capacity, Occupied, Vacant, Occupancy % — same source as Homes List / Vacancy Board / Home Dashboard (`OccupancyRepository::estate_summary()`) |
| Care delivery | Supported Living Clients, Awaiting Placement (aggregate only), Own-Home Clients, Care Setting Not Specified |
| Charts | Care Delivery Setting (doughnut); Occupancy by Home (grouped Occupied/Vacant bar + accessible table) |

Active care records reuse the Active Clients rule: `archived_at IS NULL` and `status NOT IN ('completed','cancelled')`.

Full CSV includes a **Supported Living — Current Snapshot** Metric/Value block. No client names or resident-level exports in this slice.

### Vacancy Report — Current Snapshot (Phase 2G.2)

Detailed vacant bedrooms (active homes/bedrooms only). **Not** date-range filtered.

| Feature | Behaviour |
| --- | --- |
| Summary | Capacity / Occupied / Vacant / Occupancy % (estate or selected home — same metrics as 2G.1 / Home Dashboard) |
| Table | Home, Bedroom, Location (city · postcode), Vacant Since, Status |
| Vacant Since | Latest `move_out_date`, or **Never occupied** |
| Home filter | `jmrs_report_home` — All Active Homes or one active home; vacancy section/CSV only |
| CSV | `Export Vacancy CSV` → `jmrs-supported-living-vacancies-YYYY-MM-DD.csv` |
| Caps | `jmrs_view_reports` + `jmrs_view_homes` for detailed vacancy |

No resident names or clinical data.

### Placement Movements — Selected Period (Phase 2G.3)

Date-range movement KPIs and detail from referral activity (`placement_started` / `placement_transferred` / `placement_ended`).

| Feature | Behaviour |
| --- | --- |
| Date filter | Existing Reports presets — filters on `activity.created_at` (recorded time) |
| KPIs | New Placements, Transfers, Placements Ended, Total Placement Events |
| Table | Recorded Date, Event, Referral Number, Client, Details (existing activity description) |
| Home filter | Not applied (no structured From/To on activity; deferred) |
| CSV | `Export Movements CSV` → `jmrs-placement-movements-YYYY-MM-DD.csv` |
| Caps | `jmrs_view_reports` + assigned-to AccessPolicy scope when applicable |

Does not change 2G.1 / 2G.2 current-snapshot sections.

### Visit Analytics — Care Delivery Context (Phase 2G.4)

Existing Visit KPIs / Visit Analytics filtered by care-delivery context. **Does** respect the report date range.

| Feature | Behaviour |
| --- | --- |
| Care Delivery filter | `jmrs_visit_care_context` — All / Supported Living / Client's Own Home / Unresolved / Location Not Recorded |
| Visit Home filter | `jmrs_visit_home` — All Homes, active Homes, or inactive Homes with historical snapshots in range |
| Classification | Executed + snapshot → historical location; open → current care setting / occupancy; terminal without snapshot → Location Not Recorded |
| Scope | Visit KPIs, Visit Analytics, visit-linked Task metrics, visits-completed Staff metrics, outstanding manager reviews |
| Vacancy Home | `jmrs_report_home` remains Vacancy-only — no cross-contamination |
| CSV | Visit section + Full Report preserve Visit filter params when set; no street addresses |
| Caps | `jmrs_view_reports` + assigned-to AccessPolicy scope |

No-filter (All / All Homes) matches pre-2G.4 Visit numbers. Historical executed visits never reassign to the client's current Home after transfer.

### Reporting polish (Phase 2G.5)

Filter groups on Reports: **Report Period**, **Vacancy Home**, **Visit Analytics Filters**, with **Reset Filters**. Snapshot vs period badges/copy. Empty states for no homes / no vacancies / no movements / no visit filter matches. Care Setting labels aligned to `CareSetting` (Supported Living / Client's Own Home / Not Specified). UAT checklist: `docs/uat/UAT_SUPPORTED_LIVING_REPORTING.md`.

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
