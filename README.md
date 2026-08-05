# JM Referral System

**Version:** 1.0.0 · **Database schema:** 2.17.0

A secure WordPress plugin for healthcare referral and domiciliary care management, built for J&M Healthcare.

---

## Overview

JM Referral System is a healthcare referral and care management platform designed for domiciliary care providers. It takes a referral from initial intake through assessment, care planning, team assignment, visit scheduling, and day-to-day care delivery — with role-based access, audit logging, and record-level permissions throughout.

The plugin runs inside WordPress admin, uses a modular PHP architecture (Repository → Service → Controller), and manages its own database tables via a versioned migration system.

Before go-live, use `docs/RELEASE_CHECKLIST.md`. Remaining constraints are listed in `docs/KNOWN_LIMITATIONS.md`.

---

## Features

### Referral Management

- Create, view, edit, and manage referrals
- Unique referral numbering
- Staff assignment and reassignment
- Search, filtering, and status tracking
- Full referral detail view with related clinical and operational modules
- **Public website intake:** shortcode `[jmrs_public_referral_form]` — multi-step wizard with no-JS fallback (see `docs/PUBLIC_REFERRAL_INTAKE.md`)
- **Staff portal (optional):** `/staff-portal/` foundation — dashboard, referral list/view, capability nav (see `docs/STAFF_PORTAL.md`); disabled by default

### Workflow Management

- Configurable workflow stages for the domiciliary care pathway
- Stage progression from the referral view
- Admin tools to create and maintain stages

### Client Intake

- Structured client details at referral creation
- Preferred contact methods and intake fields tailored to care referrals

### Service Types

- Configurable service types for referrals
- Active/inactive management from plugin settings areas

### Referral Sources

- Controlled referral source taxonomy
- Consistent labelling across forms, lists, and exports

### Assessments

- Structured domiciliary care assessment linked to a referral
- Create and update assessment records with activity logging

### Care Plans

- Care plans linked to referrals
- Plan creation, updates, and activation
- Supports the clinical path from assessment into ongoing care

### Care Plan Reviews

- Formal care plan reviews
- Version history for care plan changes
- Review capability separated from day-to-day plan editing

### Care Team

- Assign care team members to a referral
- Team roles, primary carer designation, and assignment status
- Visit and schedule assignment informed by the active care team

### Visit Scheduling

- Recurring visit schedules (daily, weekly, monthly, custom)
- Schedule status: active, paused, completed
- Linked care plan and care team assignment
- Weekday selection stored as a normalised JSON structure

### Generated Visits

- Expand active schedules into discrete care visit rows
- Configurable generation window with duplicate prevention
- Manual visits remain separate from schedule-generated visits
- Source labelling (Manual vs Schedule name) in the visits UI

### Documents

- Secure document upload and download per referral
- Capability-gated access for upload and download
- **Private storage (Phase 5.2.1):** new files are stored under `wp-content/uploads/jmrs-private/` with randomized filenames, served only through the plugin download controller (capability + AccessPolicy + nonce)
- Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG (max 10 MB)
- Legacy Media Library attachments remain downloadable via the same secure links; optional batch migration on **Settings** copies them into private storage without deleting originals
- `.htaccess` deny rules are written for Apache-compatible hosts; they may not apply on every server — controller-mediated downloads remain mandatory
- Backups must include both the database and `uploads/jmrs-private/`

### Notes

- Internal notes on referrals
- Visible in the referral timeline alongside other activity

### Email Notifications

- Email alerts for key referral events (for example creation, assignment, status changes)
- Template-driven notification content

### Dashboards

- Operational dashboard for managers and coordinators
- Upcoming visits overview
- Support Worker views scoped to assigned clients and activity

### Security

- WordPress capability model extended with plugin-specific caps
- Nonce-protected form actions
- Prepared SQL for database access
- Record-level access control via `AccessPolicy`

### Permissions

- Custom JM staff roles with tailored capability sets
- Support Workers scoped to referrals assigned to them
- Assessors and Support Workers remain read-only for visit generation and related management actions where caps require it

### Activity Logging

- Referral activity timeline for create/update, assignment, workflow, notes, documents, assessments, care plans, reviews, care team, schedules, and visits
- Schedule visit generation logged as a single summary entry (not one row per generated visit)

### CSV Export

- Export referrals to CSV for reporting and offline review
- Gated by the export capability; formula-injection protection on string cells
- Chunked streaming for large filtered exports

### Data Retention

- Archive / restore for referrals with clinical history (archive-first; no cascading clinical delete)
- Safe permanent delete only when a referral has no blocking dependents
- Active / Archived / All list filter; archived records excluded from ops defaults

### Reports

- **J&M Referrals → Reports** for managers and coordinators (`jmrs_view_reports`)
- Date presets (Today, This Week, This Month, This Year) and custom ranges
- KPI cards plus trends/analytics sections (referral, visit, medication, task, staff, compliance)
- Chart.js visualisations loaded only on the Reports page (local `assets/vendor/chart.umd.min.js` when present; otherwise pinned CDN Chart.js **4.4.6**)
- Full report CSV export and per-section CSV export (nonce-protected)
- Print-friendly layout via browser print (no PDF generation in this phase)
- Assessors and Support Workers do not receive report access

---

## Architecture

Clinical and operational flow:

```text
Referral
  → Assessment
  → Care Plan
  → Care Team
  → Schedule
  → Visits
```

Recurrence lives on **schedules**. Discrete care delivery rows live in **visits**. Generation expands an active schedule into visit rows without replacing manual or completed visits.

### Modular design

Each domain follows a consistent layered pattern:

| Layer | Responsibility |
| --- | --- |
| **Repository** | Database access only (prepared SQL, no business rules) |
| **Service** | Validation, business logic, activity logging |
| **Controller** | Admin routing, nonces, capability checks, redirects, notices |
| **Template** | Presentation only |

Dependency injection is wired in `src/Core/Plugin.php` and `src/Admin/Menu.php`. Namespace `JMReferral\` maps to `src/` via Composer PSR-4.

---

## Folder Structure

```text
jm-referral-system/
├── jm-referral-system.php      # Plugin bootstrap
├── composer.json               # PSR-4 autoload
├── uninstall.php
├── src/
│   ├── Core/                   # Plugin bootstrap, activator, deactivator
│   ├── Admin/                  # Admin menu and page shells
│   ├── Database/               # Tables definitions and Migrator
│   ├── Permissions/            # Roles, Capabilities, AccessPolicy
│   ├── Users/                  # User lookup helpers
│   ├── Referral/               # Referrals, notes, activity, export, filters
│   ├── Workflow/               # Workflow stages
│   ├── Services/               # Service types
│   ├── Assessment/             # Referral assessments
│   ├── CarePlan/               # Care plans, versions, reviews
│   ├── CareTeam/               # Care team assignments
│   ├── Scheduling/             # Visit schedules and generation
│   ├── Visits/                 # Care visits
│   ├── Documents/              # Secure documents
│   └── Notifications/          # Email notification services and templates
└── templates/                  # Admin UI templates
```

Important modules:

- **`Referral/`** — core referral CRUD, notes, activity timeline, list filters, CSV export
- **`Assessment/`** — clinical assessment records
- **`CarePlan/`** — care plans, version snapshots, and reviews
- **`CareTeam/`** — staff assignments on a referral
- **`Scheduling/`** — recurring schedules and visit generation (`ScheduleGenerationService`)
- **`Visits/`** — discrete care visits (manual and generated)
- **`Permissions/`** — roles, capabilities, and record-level `AccessPolicy`
- **`Database/`** — schema (`Tables`) and versioned `Migrator`

---

## Installation

### Requirements

- WordPress 6.0+ (recommended)
- PHP 8.0+
- MySQL / MariaDB
- Composer (for autoload generation during development)

### Installation steps

1. Place the plugin in `wp-content/plugins/jm-referral-system/`
2. From the plugin directory, run `composer install` (or `composer dump-autoload`) if vendor autoload is not already present
3. Activate **JM Referral System** in WordPress Admin → Plugins
4. Assign JM roles to staff users as required

On activation the plugin:

- Runs pending database migrations
- Grants plugin capabilities to the WordPress Administrator role
- Registers JM staff roles

### Migration behaviour

- Schema version is stored in the `jmrs_db_version` option
- On `plugins_loaded` (and activation), `Migrator::maybe_migrate()` compares the installed version to `Migrator::DB_VERSION`
- When behind, `Tables::create()` runs via `dbDelta` to create missing tables and add missing columns safely
- Existing rows are preserved; migrations are additive rather than destructive
- Some version bumps also re-sync capabilities and roles

---

## Roles

| Role | Slug | Purpose |
| --- | --- | --- |
| **JM Administrator** | `jmrs_administrator` | Full plugin access across referrals, clinical modules, configuration, and settings |
| **Referral Manager** | `jmrs_referral_manager` | End-to-end referral operations including archive/restore, delete (empty only), export, care plans, team, schedules, and visits |
| **Care Coordinator** | `jmrs_care_coordinator` | Day-to-day coordination: referrals, care plans, team, schedules, and visits (no delete/export/settings) |
| **Assessor** | `jmrs_assessor` | Clinical assessment and care planning; view visits/team/schedules; cannot manage visits or generate schedules |
| **Support Worker** | `jmrs_support_worker` | Read-focused access scoped to assigned referrals; dashboard for own clients and upcoming visits |

WordPress **Administrator** also receives all plugin capabilities.

---

## Capabilities

Custom capabilities (prefix `jmrs_`):

| Area | Capabilities |
| --- | --- |
| Dashboard | `jmrs_view_dashboard` |
| Referrals | `jmrs_view_referrals`, `jmrs_create_referrals`, `jmrs_edit_referrals`, `jmrs_delete_referrals`, `jmrs_archive_referrals`, `jmrs_restore_referrals`, `jmrs_assign_referrals` |
| Notes / export | `jmrs_add_notes`, `jmrs_export_referrals` |
| Documents | `jmrs_upload_documents`, `jmrs_download_documents` |
| Care plans | `jmrs_view_care_plans`, `jmrs_manage_care_plans`, `jmrs_review_care_plans` |
| Visits | `jmrs_view_visits`, `jmrs_manage_visits` |
| Care team | `jmrs_view_care_team`, `jmrs_manage_care_team` |
| Schedules | `jmrs_view_schedules`, `jmrs_manage_schedules` |
| Configuration | `jmrs_manage_service_types`, `jmrs_manage_workflow_stages`, `jmrs_manage_settings` |
| Alerts | `jmrs_view_operational_alerts` |
| Reports | `jmrs_view_reports` |
| Medications | `jmrs_view_medications`, `jmrs_manage_medications`, `jmrs_administer_medications` |

Capability checks are enforced in controllers and services. Record visibility for Support Workers is further restricted by assignment via `AccessPolicy`.

---

## Security

### Nonces

All state-changing admin forms use WordPress nonces (`check_admin_referer` / `wp_nonce_field`) scoped to the relevant referral or entity action.

### Capability checks

Controllers verify `Capabilities::current_user_can(...)` before save, generate, upload, export, and configuration actions.

### AccessPolicy

`AccessPolicy` enforces record-level rules:

- Users with unrestricted roles may view/edit referrals they have caps for
- Support Workers are scoped to referrals where `assigned_to` matches their user ID
- List queries and related modules respect the same constraint where applicable

### Prepared SQL

Repositories use `$wpdb->prepare()` for dynamic values. Table names come from trusted `Tables` helpers.

### Audit logging

Meaningful domain events are written to the referral activity timeline (create/update, assignment, workflow, notes, documents, assessments, care plans, reviews, care team, schedules, visits, schedule generation summaries, archive, and restore).

### Data retention and deletion

Archive-first policy (see `docs/DATA_RETENTION_POLICY.md`):

- Referrals with linked clinical/operational records cannot be permanently deleted; archive them instead
- Empty test referrals (no blocking dependents) may be permanently deleted by users with `jmrs_delete_referrals`
- Archived referrals are read-only and excluded by default from dashboard, alerts, and current-state reports
- Archive / restore require `jmrs_archive_referrals` / `jmrs_restore_referrals` (Administrator, JM Administrator, Referral Manager)

### Uninstall

Default plugin deletion removes JM roles and capabilities only and **preserves** custom tables and `uploads/jmrs-private/` files.

To wipe plugin data on uninstall (disposable sites only, after backups):

```php
define('JMRS_DELETE_DATA_ON_UNINSTALL', true);
```

Legacy Media Library attachments are never deleted automatically.

---

## Development

### Git

Use feature branches and clear commit messages. Do not commit secrets (`.env`, credentials). Follow the project’s existing commit style when contributing.

### Composer

```bash
composer install
composer dump-autoload
```

PSR-4 mapping:

```json
"JMReferral\\": "src/"
```

### Migration system

1. Update `Tables` schema definitions (`dbDelta`-compatible `CREATE TABLE` SQL)
2. Bump `Migrator::DB_VERSION`
3. Add any one-off sync logic in `Migrator::migrate()` when needed (capabilities/roles)
4. Activate or load the plugin so `maybe_migrate()` applies changes

Prefer additive schema changes. Do not remove or rewrite existing clinical data in place.

### Documentation

| Doc | Purpose |
| --- | --- |
| `docs/STAFF_PORTAL.md` | Staff frontend portal architecture, routes, settings, security |
| `docs/PUBLIC_REFERRAL_INTAKE.md` | Public shortcode form, settings, security, notifications |
| `docs/RELEASE_CHECKLIST.md` | Pre-production install, upgrade, and smoke checklist |
| `docs/KNOWN_LIMITATIONS.md` | Genuine remaining limitations for v1.0 |
| `docs/DATA_RETENTION_POLICY.md` | Archive-first retention and deletion rules |
| `docs/PRODUCTION_AUDIT.md` | Phase 5.1 audit + later remediation notes |
| `docs/PERFORMANCE_AUDIT.md` | Performance findings and mitigations |
| `docs/UI_STYLE_GUIDE.md` | Buttons, badges, tables, forms, colours |
| `docs/ACCESSIBILITY_REVIEW.md` | Keyboard, screen reader, remaining a11y limits |

### Testing

No automated PHPUnit suite in v1.0. Use `docs/RELEASE_CHECKLIST.md` and verify manually against:

- Role matrix (Administrator, Referral Manager, Care Coordinator, Assessor, Support Worker)
- Referral create → assessment → care plan → team → schedule → generate visits
- Support Worker scoping on lists, dashboard, and visits
- Duplicate visit generation (same window should skip existing `generation_key` rows)
- Manual visit create/edit and completion still work after schedule generation
- Empty referral permanent delete vs blocked delete when a note/assessment/visit/MAR exists
- Archive / restore; archived mutation rejected server-side
- List Active / Archived / All filter and CSV archive columns
- Uninstall without constant preserves tables; with constant on a test site removes plugin tables/files

---

## Roadmap

| Phase | Focus | Status |
| --- | --- | --- |
| **1 — Foundation** | Plugin architecture, referrals, dashboard, notes, export, notifications | Complete |
| **2 — Security** | Roles, capabilities, workflow, service types, sources, record-level access | Complete |
| **3 — Clinical operations** | Documents, assessments, care plans, scheduling, visits, MAR, alerts | Complete |
| **4 — Reporting** | Reports foundation, trends/analytics, charts, CSV exports, print | Foundation complete |
| **5 — Production** | Audit, private docs, retention, performance, UX/a11y, v1.0 release readiness | Complete (v1.0.0) |

Post-1.0 backlog (cron generation, calendar UI, portals, automated tests, timed retention) is tracked in `ROADMAP.md` and `docs/KNOWN_LIMITATIONS.md`.

---

## License

Private client project for J&M Healthcare. All rights reserved. Not licensed for public redistribution.
