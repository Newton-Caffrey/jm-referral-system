# JM Referral System Roadmap

## Vision

Build a secure, scalable, and user-friendly healthcare referral and care management system for J&M Healthcare.

---

## Completed ✅

### Referral Management ✅

- [x] Plugin architecture and database migrations
- [x] Referral CRUD and unique referral numbering
- [x] Client intake and preferred contact methods
- [x] Assignment and reassignment
- [x] Search, filtering, and CSV export
- [x] Internal notes and activity timeline
- [x] Email notifications
- [x] Dashboard
- [x] Service types and referral sources
- [x] Staff roles, custom capabilities, and record-level permissions

### Workflow ✅

- [x] Configurable workflow stages
- [x] Stage progression from the referral view
- [x] Workflow stage administration

### Documents ✅

- [x] Secure document upload and download
- [x] Capability-gated document access
- [x] Private storage for new uploads (`uploads/jmrs-private/`, Phase 5.2.1)
- [x] Legacy → private batch migration tool (Settings; originals retained)
- [x] Code-level security hardening (Phase 5.2.2: email templates, CSV formula escape, allowlists, AccessPolicy consistency)
- [ ] Legacy Media Library cleanup after migration (later hardening phase)

### Assessments ✅

- [x] Structured domiciliary care assessments
- [x] Assessment create/update with activity logging

### Care Plans ✅

- [x] Care plans linked to referrals
- [x] Care plan create, update, and activation
- [x] Care plan reviews and version history

### Care Team ✅

- [x] Care team assignments with roles and status
- [x] Primary carer designation
- [x] Team-aware visit and schedule assignment

### Scheduling ✅

- [x] Recurring visit schedules (daily, weekly, monthly, custom)
- [x] Schedule statuses: active, paused, completed
- [x] Linked care plan and care team assignment

### Generated Visits ✅

- [x] Expand active schedules into discrete care visits
- [x] Generation window, duplicate prevention, and source labelling
- [x] Manual visits preserved separately from generated visits

### Visit Execution ✅

- [x] Visit check-in / check-out and completion workflows
- [x] Structured visit task checklists from care-plan sections
- [x] Task exceptions (not completed, refused, not applicable) with notes
- [x] Auto-generated task summaries and manager review

### Operational Alerts ✅

- [x] Dynamic compliance and operational alerts (no persistence table)
- [x] Dashboard summary and dedicated Operational Alerts page
- [x] Capability-gated access for managers and coordinators

### Medication Administration ✅

- [x] Client medication list (active / paused / discontinued)
- [x] Medication administration outcomes during visit execution
- [x] Exception reason codes, dashboard counts, and operational alerts

### Reporting Foundation ✅

- [x] Capability-gated Reports admin page with date-range filters
- [x] KPI cards for referrals, clients, assessments, care plans, visits, medication, and compliance
- [x] Dashboard Reports shortcut
- [x] Trends and analytics sections with summary tables and chart-ready datasets
- [x] Chart.js charts (Reports page only)
- [x] Full and section CSV exports
- [x] Print-friendly report layout

### Data Retention & Safe Deletion ✅

- [x] Archive-first retention (`archived_at` / reason; no cascading clinical delete)
- [x] Safe permanent delete for empty referrals only
- [x] Archive / restore capabilities and UI
- [x] Active / Archived / All list filter; archived excluded from ops defaults
- [x] Settings Data Integrity Check (counts only)
- [x] Uninstall: preserve data by default; opt-in wipe constant
- [x] `docs/DATA_RETENTION_POLICY.md`

---

## In Progress 🚧

### Public Referral Intake

- [x] Phase 6.1A: shortcode form, settings, spam controls, private uploads, notifications (`docs/PUBLIC_REFERRAL_INTAKE.md`)
- [ ] Phase 6.1B: multi-step wizard UX
- [ ] CAPTCHA option / tracking portal / public edit (later)

### Medication Administration (advanced)

- [ ] Stock control, controlled-drug registers, PRN, eMAR export

### Reporting

- [ ] Staff workload views
- [ ] Scheduled/email report delivery
- [ ] Deeper referral analytics exports

### Production Hardening ✅ (v1.0.0)

- [x] Production audit (`docs/PRODUCTION_AUDIT.md`, Phase 5.1)
- [x] Private documents + security hardening (Phase 5.2)
- [x] Data retention & safe deletion (Phase 5.3)
- [x] Performance & scalability audit (`docs/PERFORMANCE_AUDIT.md`, Phase 5.4.1)
- [x] List pagination + dashboard alert/enrichment fixes (Phase 5.4.2A)
- [x] Referral View pagination / N+1 / generation batching / chunked CSV / indexes (Phase 5.4.2B)
- [x] UX polish & accessibility (Phase 5.5) — shared admin CSS/JS, badges, confirms, docs
- [x] Version 1.0 release readiness (Phase 5.6) — checklist, known limitations, docs alignment

### Hardening (post-1.0)

- [ ] Remaining performance: search strategy, Menu DI reuse, report DATE() predicates, View docs/schedules pagination
- [ ] Chart.js local vendor pin (CDN fallback remains if vendor file missing)
- [ ] Legacy Media Library cleanup after private migration
- [ ] Timed retention purge (only after legal/compliance sign-off)
- [ ] Automated test harness (PHPUnit / CI)

---

## Future 🚀

### Calendar

- [ ] Calendar interface for schedules and visits

### Recurring Synchronization

- [ ] Automatic / cron-based visit generation
- [ ] Synchronise future visits when a schedule changes
- [ ] Recurring-series exceptions

### Client Portal

- [ ] Client-facing portal for referral and care information

### Family Portal

- [ ] Family / next-of-kin access to approved care updates

### Digital Signatures

- [ ] Digital signing for assessments, care plans, and related documents

### API

- [ ] External API for integrations and partner systems

### Mobile App

- [ ] Mobile experience for Support Workers and field staff

---

## Phase History

| Phase | Focus | Status |
| --- | --- | --- |
| Phase 1 — Foundation | Architecture, referrals, dashboard, notes, export, notifications | ✅ Complete |
| Phase 2 — Security | Roles, capabilities, workflow, service types, sources, access policy | ✅ Complete |
| Phase 3 — Clinical Operations | Documents, assessments, care plans, care team, scheduling, generated visits | ✅ Complete |
| Phase 4 — Visit Execution & Medication | Field visit delivery, task checklists, operational alerts, and MAR foundation | ✅ Complete |
| Phase 5 — Reporting | Reporting foundation KPIs; advanced reports, workload, analytics next | ✅ Foundation complete |
| Phase 5.x — Production Hardening | Audit → private docs → retention → performance → UX/a11y → v1.0 readiness | ✅ Complete (v1.0.0) |
| Phase 6 — Platforms & Integrations | Calendar, sync, portals, signatures, API, mobile | 🚀 Future |
| Phase 6.1 — Public referral intake | 6.1A foundation done; 6.1B wizard next | 🚧 In progress |
| Post-1.0 hardening | Remaining performance, Chart.js local pin, legacy media cleanup, automated tests | 🚧 Backlog |

**Current release:** plugin `1.0.0` · DB `2.17.0` (after public intake migration) · see `docs/RELEASE_CHECKLIST.md` and `docs/KNOWN_LIMITATIONS.md`.

See `CHANGELOG.md` for release detail and `README.md` for current system documentation.
