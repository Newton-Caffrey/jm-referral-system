# Changelog

All notable changes to the JM Referral System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-08-04

Milestone release covering the foundation, security, and clinical operations delivered so far: referrals through assessments, care plans, care team, scheduling, and generated visits.

### Added

- Plugin architecture with Composer PSR-4 autoloading (`JMReferral\` → `src/`)
- Versioned database migration system (`Migrator`, `Tables`, `jmrs_db_version`)
- Repository → Service → Controller → Template layering with dependency injection
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

### Changed

- Care pathway flow standardised as Referral → Assessment → Care Plan → Care Team → Schedule → Visits
- Schedule `days_of_week` storage normalised to a JSON array of lowercase weekday keys (for example `["monday","wednesday","friday"]`), with backward-compatible decoding of legacy comma-separated values and ISO weekday numbers
- `days_of_week` column widened to `VARCHAR(191)` to support full JSON weekday arrays
- Care visits table extended with nullable schedule source fields: `schedule_id`, `schedule_occurrence_date`, `generation_key`
- Generated visit inserts are insert-only and do not rewrite manual, completed, cancelled, or missed visits
- Schedule visit generation logs a single `schedule_visits_generated` activity entry instead of one `visit_created` entry per generated visit
- Database schema advanced through additive migrations (current schema version `2.8.1`)

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
