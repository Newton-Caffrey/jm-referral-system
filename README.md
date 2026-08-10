# JM Referral System

**Version 1.3.0** · **Database schema 2.28.0**

A secure WordPress plugin for healthcare referral and domiciliary care management, built for **J&M Healthcare**.

---

## Overview

JM Referral System takes a referral from website or admin intake through the **acquisition pipeline** (interest → assessment → package cost → LA decision → transition → care commencement), then assessment/care planning, team assignment, scheduling, visit delivery, and medication administration — with role-based access, audit activity, private documents, optional staff portal access, and **Supported Living** homes/occupancy operations.

Architecture follows **Repository → Service → Controller → Template** with dependency wiring in `Plugin.php`, versioned schema migrations, and capability + AccessPolicy enforcement.

<!-- Screenshot: admin dashboard -->
<!-- Screenshot: referral view -->
<!-- Screenshot: public wizard -->
<!-- Screenshot: staff portal -->

---

## Features

- Referral CRUD, numbering, assignment, search/filter, CSV export
- Workflow stages, notes, activity timeline, email notifications
- Assessments, care plans (reviews/versions), care team
- Schedules, visit generation/execution, task checklists
- Medication list and visit-time administration
- Operational alerts and reporting (including Supported Living analytics)
- Private document storage and secure downloads
- Public intake shortcode with multi-step wizard
- Optional staff portal (referral edit + clinical day-to-day workflows)
- Supported Living: Homes, bedrooms, occupancy, transfers, own-home care setting, home dashboard
- Archive / restore / safe delete; data integrity counts

---

## Requirements

- WordPress 6.0+
- PHP 8.0+ (8.1+ recommended)
- Working `wp_mail` / SMTP for notifications
- HTTPS recommended

---

## Installation

1. Upload the release ZIP (must include `vendor/`) under **Plugins → Add New**.
2. Activate **J&M Referral System**.
3. Configure SMTP, then Settings (public form / portal as needed).

Full steps: [`docs/INSTALLATION_GUIDE.md`](docs/INSTALLATION_GUIDE.md).
Go-live ticks: [`docs/RELEASE_CHECKLIST.md`](docs/RELEASE_CHECKLIST.md).

### Shortcodes

```
[jmrs_public_referral_form]
```

### Portal

Default path `/staff-portal/` — disabled until enabled in **Settings → Staff Portal**. Supports referral editing and clinical operations (care-plan review, medications, care team, schedules, visits/execute/MAR, manager review) via shared services — not a fork of wp-admin logic. See [`docs/STAFF_PORTAL.md`](docs/STAFF_PORTAL.md).

---

## Architecture

| Layer | Location |
| --- | --- |
| Bootstrap | `jm-referral-system.php`, `src/Core/` |
| Domain | `src/Referral`, `Assessment`, `CarePlan`, `Visits`, … |
| Permissions | `src/Permissions` |
| Portal | `src/Portal` |
| Public intake | `src/Frontend` |
| Templates | `templates/` |
| Assets | `assets/css`, `assets/js` |

Schema version: option `jmrs_db_version` / `Migrator::DB_VERSION` (independent of product semver).

---

## Security

- Capability checks and AccessPolicy
- Private uploads; secure download controllers
- Public form spam controls
- Portal privacy headers

Report vulnerabilities privately — see [`SECURITY.md`](SECURITY.md) and [`docs/SECURITY.md`](docs/SECURITY.md).

---

## Documentation

| Guide | Description |
| --- | --- |
| [Installation](docs/INSTALLATION_GUIDE.md) | Install, SMTP, portal, upgrades |
| [Administrator](docs/ADMINISTRATOR_GUIDE.md) | Full admin feature map |
| [Staff user](docs/STAFF_USER_GUIDE.md) | Portal usage |
| [Public referral](docs/PUBLIC_REFERRAL_GUIDE.md) | Website form |
| [Release notes v1.3.0](docs/RELEASE_NOTES_v1.3.0.md) | Current release highlights |
| [Release notes v1.2.0](docs/RELEASE_NOTES_v1.2.0.md) | Supported Living release |
| [Release notes v1.0.0](docs/RELEASE_NOTES_v1.0.0.md) | First production package |
| [Supported Living](docs/SUPPORTED_LIVING.md) | Homes, occupancy, service location, reporting |
| [Packaging](docs/PACKAGING.md) | Production ZIP build rules |
| [Troubleshooting](docs/TROUBLESHOOTING.md) / [FAQ](docs/FAQ.md) | Support |
| [Known limitations](docs/KNOWN_LIMITATIONS.md) | Honest constraints |
| [Backup & recovery](docs/BACKUP_AND_RECOVERY.md) | Ops continuity |
| [Release checklist](docs/RELEASE_CHECKLIST.md) | Pre-production gate |
| [UAT package](docs/uat/README.md) | Acceptance tests (Phase 3 + Supported Living) |

Additional technical docs: performance/production audits, retention policy, UI/a11y, portal/intake deep-dives under `docs/`.

**v1.3.0 note:** Production promotion requires completed Phase 3 UAT (`docs/uat/UAT_PHASE_3.md`) and the release checklist gate. Evidence stays outside Git (`uat-evidence/`).

### Developer documentation

| Doc | Description |
| --- | --- |
| [Architecture](docs/developer/ARCHITECTURE.md) | Layers, request flows, design decisions |
| [Database schema](docs/developer/DATABASE_SCHEMA.md) | Tables, indexes, relationships |
| [Permissions](docs/developer/PERMISSIONS.md) | Caps, roles, AccessPolicy |
| [Workflows](docs/developer/WORKFLOWS.md) | Domain lifecycles |
| [Portal architecture](docs/developer/PORTAL_ARCHITECTURE.md) | Staff portal internals |
| [Public referral architecture](docs/developer/PUBLIC_REFERRAL_ARCHITECTURE.md) | Wizard, spam, PRG |
| [Services](docs/developer/SERVICES.md) | Service catalogue |
| [Dependency injection](docs/developer/DEPENDENCY_INJECTION.md) | Plugin/Menu composition root |
| [Project history](docs/developer/PROJECT_HISTORY.md) | Milestone timeline → 1.1 |

---

## Production ZIP contents

**Include**

- Plugin PHP, `templates/`, `assets/`, `docs/`
- `vendor/` (Composer runtime autoload; run `composer install --no-dev` before packing)
- `LICENSE`, `README.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`

**Exclude**

- `.git/`, `.github/`
- `node_modules/`
- OS junk (`.DS_Store`, `Thumbs.db`)
- Editor/IDE configs (`.idea/`, `.vscode/` unless intentionally shared)
- Local env files, dumps, personal notes
- Dev-only tooling not required at runtime

Suggested archive root folder name: `jm-referral-system/`.

---

## Roadmap

**Current release:** plugin `1.3.0` · DB `2.28.0` — see [`docs/RELEASE_NOTES_v1.3.0.md`](docs/RELEASE_NOTES_v1.3.0.md) and [`ROADMAP.md`](ROADMAP.md).
Supported Living UAT: [`docs/uat/UAT_SUPPORTED_LIVING_V1_2.md`](docs/uat/UAT_SUPPORTED_LIVING_V1_2.md).

---

## Support

Internal J&M Healthcare operations / your appointed maintainer.
Security: `SECURITY.md`.
Contributing: `CONTRIBUTING.md`.

---

## License

Licensing to be decided — see [`LICENSE`](LICENSE). All rights reserved by J&M Healthcare until a licence is published.
