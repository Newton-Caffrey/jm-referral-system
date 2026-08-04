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

---

## In Progress 🚧

### Medication Administration

- [ ] Medication records linked to care delivery
- [ ] Administration logging and review

### Reporting

- [ ] Advanced reports
- [ ] KPI dashboard
- [ ] Staff workload views
- [ ] Referral analytics and performance metrics

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
| Phase 4 — Visit Execution & Medication | Field visit delivery, task checklists, operational alerts, and medication administration | 🚧 In progress |
| Phase 5 — Reporting | Advanced reports, KPIs, workload, analytics | 🚧 In progress |
| Phase 6 — Platforms & Integrations | Calendar, sync, portals, signatures, API, mobile | 🚀 Future |
| Phase 7 — Production Hardening | Performance, UX polish, automated testing, v1.0 | 🚀 Future |

See `CHANGELOG.md` for release detail and `README.md` for current system documentation.
