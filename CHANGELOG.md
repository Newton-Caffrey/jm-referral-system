# Changelog

All notable changes to the JM Referral System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Fixed

## [1.4.0] - 2026-08-11

Feature release: **J&M Healthcare Management Dashboard** — senior-management presentation of the live referral-to-placement pipeline over real JMRS data. Plugin version `1.4.0`. Database schema remains `2.28.0` (no migration). Portal rewrite `1.2.1` → `1.2.2` for the new `/management/` route.

Canonical acquisition workflow, existing operational Dashboard, Pipeline Attention rules, and schema are unchanged. Visual stages are presentation groups only.

### Added

- Staff portal **Management Dashboard** at `/{portal-base}/management/` (separate from the existing Dashboard)
- Management masthead, KPI cards, six-stage visual acquisition funnel, and stage panels
- **Here now** / **All who reached** views (reach uses `jmrs_referral_stage_history` only; no fabricated legacy milestones)
- Homes & Capacity view from real Supported Living occupancy architecture
- Ownership view from live acquisition `assigned_to` counts
- Recommended Actions view reusing existing `PipelineAttentionService` / Needs Attention (no prototype thresholds)
- Print-friendly dashboard styling
- Read-only aggregators: `VisualStageMap`, `ManagementPipelineBoardService`
- Scoped Management Dashboard CSS/JS and route-only Google Fonts load

### Changed

- Portal rewrite version `1.2.1` → `1.2.2` (auto-flush when stored option lags; not on every request)
- Product version `1.3.1` → `1.4.0` (`jm-referral-system.php` / `JMRS_VERSION`)

### Clarifications

- Financial presentation uses **Proposed Package Value** (latest Package Cost `package_total` for live acquisition referrals only) — not revenue
- `care_commenced` is excluded from live Placement Transition and from live Proposed Package Value
- Prototype-only sample data, Edit Data / JSON editor, localStorage, and deferred prototype metrics (champion, attendees, LA entity, pre-visits, hours/rates, Funding Authority Performance, full Team Performance) are **not** shipped

No database migration. No canonical stage remapping. No backfill of stage history.

## [1.3.1] - 2026-08-11

Public referral form wording/UX clarification for Local Authority users. Plugin version `1.3.1`. Database schema remains `2.28.0`. Portal rewrite remains `1.2.1`.

### Changed

- Public form welcome heading: “Make a Referral” → “Local Authority Referral Form”
- Public form intro: Local Authority audience line, retaining 5–10 minute and incomplete-answer guidance; legacy product-default intro remapped on display without overwriting custom branding
- Start button: “Start Referral” → “Start Local Authority Referral”
- Small responsive adjustment so the longer primary button wraps cleanly on narrow screens
- Product version `1.3.0` → `1.3.1` (`jm-referral-system.php` / `JMRS_VERSION`)

No workflow, database, security, email, API, or business-logic changes.

## [1.3.0] - 2026-08-11

Product release for the **Referral Acquisition Pipeline** (Phase 3) on top of Supported Living v1.2.0. Plugin version `1.3.0`. Database schema `2.28.0`. Portal rewrite `1.2.1`.

### Added

#### Referral Acquisition Pipeline

- Canonical acquisition pipeline stages: Interest Response Required → Assessment to Schedule → Assessment Scheduled → Assessment Outcome Review (where required) → Package Cost to Prepare → Awaiting Local Authority Decision → Transition Planning → Placement / Care Commenced, plus terminal Declined and Not Proceeding
- Structured stage history (`jmrs_referral_stage_history`) with `created` / `transition` / `override` change types
- Express Interest with structured milestone timestamps and referrer/LA confirmation email
- Assessment scheduling (schedule / reschedule / needs rescheduling) and assessment completion
- Assessment Outcome Review path for not-suitable outcomes
- Package Cost preparation (document + proposed total GBP)
- Package Cost email send with current private-document attachment (temporary mail copy + cleanup)
- Local Authority Decision recording (Approved / Declined / Not Proceeding) with funding confirmation distinct from approval
- Mark Not Proceeding from eligible acquisition stages
- Transition Planning with Supported Living placement integration and Own Home readiness
- Explicit Confirm Care Commencement (`care_commenced_at` / `care_commenced_by`) — not inferred from occupancy, visits, or schedules
- Pipeline Dashboard & Needs Attention (operational queue)
- Optional Internal Pipeline Targets (blank defaults; not hard-coded SLAs)
- Acquisition Pipeline reporting on existing WP Admin Reports (referral cohort by `created_at`, funnel/outcomes, timing medians, assessment/package/funding summaries, stage durations, structured Phase 3 identity via history `created`)
- Acquisition CSV export (commercial/ops columns; capability + nonce)

#### Integration

- Continues to use existing Supported Living homes/bedrooms/occupancies, Own Home care setting, care plans, care teams, schedules, visits, and MAR after care commencement

### Changed

- Product version `1.2.0` → `1.3.0` (`jm-referral-system.php` / `JMRS_VERSION`)
- Database schema `2.21.0` → `2.28.0` via additive migrations (direct upgrade from production v1.2.0 supported)
- Portal rewrite remains `1.2.1` (no flush required for this release)

### Fixed

- Acquisition cohort identity uses structured pipeline-start history (`change_type=created`), so legacy referrals override-moved onto a canonical stage are not mixed into Phase 3 conversion denominators

### Schema

- DB `2.22.0` pipeline foundation → `2.23.0` interest milestones → `2.24.0` assessment scheduling → `2.25.0` package costs → `2.26.0` LA decisions → `2.27.0` assessment review stage → `2.28.0` care commencement columns

Upgrade from released v1.2.0 (DB `2.21.0`) applies all steps in one activation/migrate pass. Legacy referrals are not remapped; no fake milestones or migration emails.

See `docs/RELEASE_NOTES_v1.3.0.md`, `docs/uat/UAT_PHASE_3.md`, `docs/developer/WORKFLOWS.md`, and `docs/PACKAGING.md`.

## [1.2.0] - 2026-08-09

Product release for **Supported Living** and related staff-portal clinical/UX work delivered after v1.0.0. Plugin version `1.2.0`. Database schema `2.21.0`.

### Added

#### Supported Living

- Homes and bedrooms foundation (`jmrs_homes` / `jmrs_bedrooms`) with capacity = active bedrooms
- Occupancy and vacancy management (`jmrs_occupancies`): place, transfer, end placement, history
- Care Setting (`supported_living` / `own_home` / Not Specified) with Client's Own Home support
- Home operational dashboard (residents, bedroom board, upcoming visits, operational attention)
- Service-location resolution for current care delivery context
- Historical visit-location snapshots on execution (frozen; transfers do not rewrite history)
- Own-home address management on authorised referral edit
- Supported Living reporting on existing WP Admin Reports: current snapshot, vacancy report, placement movements, visit analytics by care-delivery context

#### Staff portal & channels (post-1.0)

- Staff portal clinical operations (care-plan review, medications, care team, schedules/generate, visits/execute/MAR, manager review)
- Portal referral editing via shared update pipeline
- Portal UX polish (partials, navigation, dashboard KPIs, mobile breakpoints)
- Public referral wizard theme isolation and visual parity hardening
- UAT packages under `docs/uat/` (v1.1 portal package + Supported Living v1.2 master checklist)

### Changed

- Product version aligned to `1.2.0` (`jm-referral-system.php` / `JMRS_VERSION`)
- Portal rewrite versions `1.1.2` → `1.2.0` → `1.2.1` for homes/occupancy routes (auto-flush when mismatched)
- Reports filters clarified (Report Period / Vacancy Home / Visit Analytics) with Reset Filters
- Phase 2H release preparation: master UAT, release checklist gate, packaging/docs alignment

### Fixed

- Phase 2D composition-root / dependency-wiring activation regression (occupancy services available after activate)
- Referrer grid long-email layout wrapping on portal referral view
- Public wizard hang/visibility hotfixes (email deferred to `shutdown`; `[hidden]` step actions preserved)

### Schema

- DB `2.18.0` homes/bedrooms → `2.19.0` occupancies → `2.20.0` care_setting → `2.21.0` visit service-location snapshot columns

See `docs/SUPPORTED_LIVING.md`, `docs/RELEASE_NOTES_v1.2.0.md`, and `docs/uat/UAT_SUPPORTED_LIVING_V1_2.md`.

## [1.0.0] - 2026-08-05

First production release of the JM Referral System. Plugin version `1.0.0`. Database schema `2.17.0`.

### Added

#### Phase 7.1 â€” release package

- User/ops docs: installation, administrator, staff, public referral, security, backup/recovery, troubleshooting, FAQ, release notes
- `LICENSE` (placeholder pending final licence), `CONTRIBUTING.md`, root `SECURITY.md`
- Professional README rewrite; packaging guidance

#### Phase 6.x â€” channels

- Phase 6.2A: staff frontend portal foundation (`/staff-portal/` by default) â€” auth, shell, capability nav, dashboard, referral list/view
- Portal settings (enable, branding, base path, support contacts, optional wp-admin redirect) under Settings â†’ Staff Portal
- Portal referral view read-only field parity with admin
- `docs/STAFF_PORTAL.md`
- Phase 6.1B: multi-step public referral wizard (Welcome â†’ About You â†’ Person â†’ Care Needs â†’ Documents â†’ Review)
- Public branding settings (company name, heading, intro, contact, primary colour, success next-steps) via `PublicBranding`
- Wizard progress indicator, review summary cards with Edit, optional analytics CustomEvents (step number only)
- Phase 6.1A: public referral intake shortcode `[jmrs_public_referral_form]`
- Public intake settings (enable, privacy URL, consent version, notification email, success message, uploads)
- Public referrer/client/consent fields and `submission_channel` (DB `2.17.0`)
- Ops + referrer confirmation email templates for website submissions
- `docs/PUBLIC_REFERRAL_INTAKE.md`

#### Phases 1â€“5.6 â€” core

- Phase 5.6: `docs/RELEASE_CHECKLIST.md` and `docs/KNOWN_LIMITATIONS.md`
- Phase 5.5: shared admin CSS/JS (`assets/css/admin.css`, `assets/js/admin.js`) on all plugin screens
- Phase 5.5: `UiHelper` badges and empty states; `docs/UI_STYLE_GUIDE.md` and `docs/ACCESSIBILITY_REVIEW.md`
- Phase 5.4.2B: Referral View visit pagination (20/50/100) with SQL LIMIT/OFFSET
- Phase 5.4.2B: bulk visit task / MAR / schedule-name / staff-name loading on View
- Phase 5.4.2B: schedule generation batch insert + batch visit tasks; hard max 2,000 occurrences/request
- Phase 5.4.2B: chunked referral CSV export (500-row chunks)
- Composite performance indexes (DB `2.16.0`)
- Phase 5.4.2A: referral list pagination (20/50/100) with filter-preserving links
- Phase 5.4.1 performance & scalability audit (`docs/PERFORMANCE_AUDIT.md`) â€” analysis only
- Phase 5.3 data retention: archive / restore / safe permanent delete (`ReferralRetentionService`)
- Referral columns `archived_at`, `archived_by`, `archive_reason` (DB `2.15.0`)
- Capabilities `jmrs_archive_referrals` and `jmrs_restore_referrals` (WP Admin, JM Admin, Referral Manager)
- Referral list Active / Archived / All filter (default Active)
- Settings â†’ Data Integrity Check (counts only)
- `docs/DATA_RETENTION_POLICY.md`
- Uninstall opt-in data wipe via `JMRS_DELETE_DATA_ON_UNINSTALL`
- Private referral document storage under `uploads/jmrs-private/` (Phase 5.2.1)
- Document table columns: `storage_type`, `relative_path`, `stored_name`, `checksum_sha256` (DB `2.14.0`)
- Settings â†’ Private Document Migration batch tool (copies legacy Media Library files; does not delete originals)
- Apache `.htaccess` / `index.php` / `index.html` protection files for the private directory
- `EmailTemplateResolver` for canonical email templates (Phase 5.2.2)
- `CsvExportHelper` for CSV formula-injection protection (Phase 5.2.2)
- `InputAllowlist` helper for reusable request allowlists (Phase 5.2.2)

### Changed

- Phase 7.1: documentation and packaging aligned for production distribution
- Public form remains one native HTML POST; JS only controls wizard step visibility
- Confirmation / success copy uses configured company branding
- Referral create path accepts public-intake columns without forking admin create
- Referral View / list / CSV export surface website channel and public fields
- CSV export includes referral source and public-intake columns
- Phase 5.6: product version locked to `1.0.0`; schema tracked independently (`2.17.0` after public intake)
- Consistent notice wording (`â€¦ successfully.` / `Please fix the following errors:`)
- Standardized primary/secondary/danger buttons, confirms (`data-jmrs-confirm`), and double-submit busy labels
- Priority/status/archive/alert badges and improved empty states on list, view, and alerts
- Referral View GET no longer generates visit tasks (create/generate paths only)
- Activity/notes limited to 50; care-plan reviews/versions to 25; version list omits snapshot payloads
- Schedule generation validates once, prefetches care plan, bulk-skips existing `generation_key`s
- Permanent Delete removed from referral list and dashboard (View-only when retention allows)
- Dashboard calculates operational alerts once and reuses counts for the reports shortcut
- Dashboard visit/task widgets JOIN client names and batch staff display names (no per-row referral finds)
- WP_DEBUG logs generic list/dashboard/view/generation/export metrics only (no SQL/PHI)
- Permanent referral delete blocked when linked clinical/operational records exist (archive instead)
- Archived referrals excluded by default from dashboard, alerts, scheduling counts, and current-state report metrics
- Clinical mutation services reject changes on archived referrals (`AccessPolicy::can_mutate_referral`)
- CSV referral export includes Archived / Archived At / Archive Reason and follows archive filter
- Default uninstall preserves tables and private files
- New uploads no longer create public Media Library attachments
- Document downloads stream from private storage or legacy attachments with stricter cache headers
- Settings page documents storage design, server access-control limits, and backup requirements
- Email templates resolve from `src/Notifications/Templates/` on Linux and Windows
- Referral create/edit sanitize status, referral source, and preferred contact against allowlists
- Referral delete and document upload require `can_edit_referral` / mutate checks
- MAR witness user IDs must be capability-bearing staff
- Security-related download/export failures use generic user messages

### Fixed

- Public form submit hang: preserve submitter and defer disable so native POST proceeds
- Portal medication and related view fields use admin array keys (`dosage`, `medication_status`, `visit_status`, â€¦)
- Phase 5.6: notes on archived referrals require `can_mutate_referral` (archive read-only alignment)
- Phase 5.5 double-submit guard: preserve clicked submit button name/value via hidden input before disabling (fixes Update Referral and other named submit actions)
- Removed unused `MedicationAdministrationController` stub and dead `get_dashboard_alerts()` wrapper

### Security

- Portal: authenticated routes, AccessPolicy on records, privacy cache headers, generic 403/404
- Optional wp-admin redirect for non-administrator JM staff (AJAX/admin-post/downloads/exports preserved)
- Public form: nonce, honeypot, minimum completion time, hashed rate limit (no raw IP storage)
- Public uploads use private storage only; never Media Library
- Wizard does not weaken spam controls or introduce AJAX submission
- Archive-first retention reduces accidental permanent deletion of referrals with health records
- New sensitive files are not exposed via public uploads URLs or the Media Library
- Legacy documents remain potentially public until migrated and originals are cleaned up in a later phase
- CSV exports neutralize leading `= + - @` / tab / CR in string cells
- Email template load failures log a generic message only (no filesystem paths)

## [0.4.0] - 2026-08-04

Milestone release covering the foundation, security, and clinical operations delivered so far: referrals through assessments, care plans, care team, scheduling, and generated visits.

### Added

- Plugin architecture with Composer PSR-4 autoloading (`JMReferral\` â†’ `src/`)
- Versioned database migration system (`Migrator`, `Tables`, `jmrs_db_version`)
- Repository â†’ Service â†’ Controller â†’ Template layering with dependency injection
- Referral CRUD with unique referral numbering
- Client intake fields and preferred contact methods
- Referral assignment and reassignment
- Search and filtering on the referrals list
- Configurable workflow stages and stage updates from the referral view
- Configurable service types
- Controlled referral sources
- Operational dashboard (including upcoming visits and Support Worker client views)
- Internal notes on referrals
- Referral activity timeline across clinical and operational events
- CSV export of referrals
- Email notifications for key referral events (creation, assignment, status changes)
- Secure document upload and download per referral
- Structured domiciliary care assessments linked to referrals
- Care plans linked to referrals (create, update, activate)
- Care plan reviews and version history
- Care team assignments with roles, primary carer, and assignment status
- Care visits foundation (manual create/edit, statuses, completion)
- Visit scheduling engine (daily, weekly, monthly, custom recurrence)
- Schedule statuses: active, paused, completed
- Generate care visits from active schedules with a configurable date window
- Duplicate prevention for generated visits via `generation_key`
- Visit source labelling (Manual vs Schedule name) in lists and edit screens
- Custom JM staff roles: JM Administrator, Referral Manager, Care Coordinator, Assessor, Support Worker
- Custom capabilities for dashboard, referrals, notes, export, documents, care plans, reviews, visits, care team, schedules, service types, workflow stages, and settings
- Project README documenting architecture, roles, capabilities, security, and installation
- Admin UI templates for referrals, assessments, care plans, care team, schedules, visits, services, workflow, and dashboard
- Visit execution with arrival/departure, outcomes, duration, manager review, and activity logging (`visit_executed`, `visit_reviewed`)
- Visit task checklists generated from care-plan sections (`wp_jmrs_visit_tasks`)
- Structured task statuses: pending, completed, not_completed, refused, not_applicable
- Auto-generated read-only task summaries (Completed / Outstanding / Refused) on visit save
- Dashboard widgets: Top Outstanding Task Types (managers) and Today's Outstanding Tasks (Support Workers)
- Activity event `visit_tasks_updated` when a visit task checklist is saved
- Operational alerts engine with dynamic compliance/ops checks (no alerts table)
- Capability `jmrs_view_operational_alerts` for Administrators, JM Administrators, Referral Managers, and Care Coordinators
- Dedicated Operational Alerts admin page with severity/type filters and search
- Dashboard Operational Alerts summary cards and grouped alert lists
- Medication Administration Records (MAR) foundation: client medication list and visit administration outcomes
- Capabilities `jmrs_view_medications`, `jmrs_manage_medications`, and `jmrs_administer_medications`
- Dashboard widgets for Medication Exceptions Today / My Medication Exceptions Today
- Operational alert for medication administration exceptions
- Reporting foundation with KPI cards and date-range filters
- Capability `jmrs_view_reports` for Administrators, JM Administrators, Referral Managers, and Care Coordinators
- Dashboard Reports shortcut card
- Report trends and analytics sections (referral, visit, medication, task, staff, compliance) with chart-ready datasets
- Chart.js visualisations on the Reports page (page-scoped enqueue; Chart.js 4.4.6 local or pinned CDN)
- Full report and per-section CSV exports with nonce protection
- Print-friendly Reports layout

### Changed

- Care pathway flow standardised as Referral â†’ Assessment â†’ Care Plan â†’ Care Team â†’ Schedule â†’ Visits
- Schedule `days_of_week` storage normalised to a JSON array of lowercase weekday keys (for example `["monday","wednesday","friday"]`), with backward-compatible decoding of legacy comma-separated values and ISO weekday numbers
- `days_of_week` column widened to `VARCHAR(191)` to support full JSON weekday arrays
- Care visits table extended with nullable schedule source fields: `schedule_id`, `schedule_occurrence_date`, `generation_key`
- Care visits table extended with execution fields (arrival, departure, outcome, task summaries, review)
- Visit execution UI uses structured task checklists instead of free-text Tasks Completed / Tasks Not Completed
- Visit execution can record medication administrations alongside visit completion
- Generated visit inserts are insert-only and do not rewrite manual, completed, cancelled, or missed visits
- Schedule visit generation logs a single `schedule_visits_generated` activity entry instead of one `visit_created` entry per generated visit
- Database schema advanced through additive migrations (current schema version `2.13.0`)

### Security

- WordPress nonce protection on state-changing admin actions
- Plugin capability checks on controllers and services before mutate/generate/export actions
- Record-level `AccessPolicy` for referral view/edit, including Support Worker scoping to assigned referrals
- Prepared SQL in repositories; no arbitrary SQL in controllers or templates
- Capability-gated document upload and download
- Role-based restrictions so Assessors and Support Workers remain read-only for schedule visit generation and related management capabilities they do not hold
- Activity/audit logging for referral, clinical, scheduling, and visit events

### Fixed

- Email notification template path resolution
- Notification delivery reliability
- Document upload MIME type compatibility
- Weekly/custom schedule visit generation failing when selected weekdays could not be decoded from stored `days_of_week` values
- Support Workers not seeing Medication Administration during visit execution when they had administer rights and visit ownership; UI now uses `jmrs_administer_medications`, visit `assigned_user_id` ownership for scoped users, AccessPolicy, and active medications valid on the visit date (matched on save)

## [0.2.0]

### Added

- Referral CRUD
- Dashboard
- Search and filtering
- CSV export
- Email notifications
- Internal notes
- Activity timeline
- Referral assignment
- Workflow stages
- Service types
- Referral sources
- Client intake details
- Staff roles
- Custom capabilities
- Record-level permissions
- Secure document management

### Fixed

- Email template path issue
- Notification delivery
- Document upload MIME compatibility

## [0.1.0]

### Added

- Initial plugin architecture
- Composer autoloading
- Database migration system
- Repository / Service / Controller architecture
