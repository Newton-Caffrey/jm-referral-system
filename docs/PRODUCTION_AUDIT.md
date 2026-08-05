# JM Referral System — Production Readiness Audit

**Phase:** 5.1 (Audit Only)  
**Date:** 2026-08-05  
**Plugin path:** `jm-referral-system`  
**Environment context:** WordPress 7.0.2 · PHP 8.5.9 · one.com Linux · ZIP deploy with `vendor/` · GitHub source control · sensitive health/care data  

**Scope:** Full codebase review. No implementation changes in this phase.  
**Disclaimer:** This is a technical engineering audit. It does **not** claim GDPR, CQC, NHS DSPT, or medical-device compliance.

---

# Executive Summary

The JM Referral System is a substantial WordPress admin plugin covering referrals through clinical operations (assessments, care plans, care team, schedules, visits, MAR), operational alerts, and reporting. Controller-level security is **generally consistent**: nonce + capability checks are present on nearly all mutating actions, and Support Worker record scoping is centralised in `AccessPolicy`.

However, the plugin is **not production-ready for live health/care data** until Critical items are addressed. The highest risks are:

1. Client documents stored as normal WordPress Media Library attachments under public `uploads/` URLs (ACL bypass via direct URL).
2. Email notification templates resolving to a case-mismatched path that fails on Linux (one.com).
3. Referral delete / uninstall leaving child PHI in the database and on disk.
4. Unpaginated referral lists/exports and an N+1-heavy referral View page that will not scale.
5. Version labelling that presents the plugin as `1.0.0` while the changelog documents `0.4.0`.

**Finding counts**

| Severity | Count |
| --- | ---: |
| Critical | 4 |
| High | 12 |
| Medium | 18 |
| Low | 14 |
| Informational | 10 |
| **Total** | **58** |

---

# Release Recommendation

**Ready After Critical Fixes**

Do **not** place live client health data into production until Critical findings (and priority High security/data-lifecycle items) are remediated and re-tested. After Critical + selected High fixes, proceed to **controlled staging** on one.com with synthetic/anonymised data, then Phase 5.2+ hardening.

---

# Critical Findings

### AUDIT-C-001 — Client documents publicly reachable via Media Library / uploads

| Field | Detail |
| --- | --- |
| **Severity** | Critical → **Partially remediated (Phase 5.2.1)** |
| **Status** | New uploads use private storage (`storage_type = private_file`) under `uploads/jmrs-private/` with controller-only downloads. Legacy rows remain `legacy_attachment` until Settings batch migration; original Media Library files are **not** deleted in 5.2.1. |
| **Category** | Security / Healthcare data |
| **Affected** | `PrivateDocumentStorage`, `ReferralDocumentService`, `DocumentMigrationController`, `SettingsPage`, DB `2.14.0` |
| **Explanation** | Previously, uploads created standard attachment posts in public `wp-content/uploads/{Y}/{m}/`. New files no longer use Media Library. Legacy public URLs may still work until migration + later cleanup. |
| **Real-world risk** | Residual risk for unmigrated legacy files and hosts where `.htaccess` is ignored. |
| **Recommended fix (remaining)** | Run Settings migration for all legacy docs; later phase: remove/restrict original attachments; nginx deny rules where needed. |
| **Hardening phase** | 5.2.1 (done for new files) / later cleanup |
| **Break risk** | Medium for cleanup phase — do not delete originals until private copies verified. |

### AUDIT-C-002 — Email template path case fails on Linux hosting

| Field | Detail |
| --- | --- |
| **Severity** | Critical |
| **Category** | Compatibility / Reliability |
| **Affected** | `src/Notifications/EmailNotificationService.php` (path `…/templates/`); actual directory `src/Notifications/Templates/` |
| **Explanation** | Code looks for lowercase `templates/`; repository folder is `Templates/`. Works on case-insensitive Windows; **fails on one.com Linux**, returning empty email body. |
| **Real-world risk** | Assignment/creation/status emails silently empty or useless in production. |
| **Recommended fix** | Align path with actual folder (or rename folder to lowercase) and add a smoke test on Linux. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low if path corrected carefully. |

### AUDIT-C-003 — Referral delete does not cascade child PHI

| Field | Detail |
| --- | --- |
| **Severity** | Critical |
| **Category** | Data integrity / Healthcare data |
| **Affected** | `ReferralRepository::delete`, `ReferralListController::handle_delete`; child tables (notes, activity, documents, assessments, care plans/versions/reviews, visits, tasks, schedules, care team, medications, MAR) |
| **Explanation** | Deleting a referral removes only the `jmrs_referrals` row. No FK constraints; no application cascade; documents and WP attachments remain. |
| **Real-world risk** | Orphaned health records remain queryable/restorable inconsistently; incomplete erasure; reporting/alerts may behave oddly. |
| **Recommended fix** | Explicit cascade (or soft-delete) with attachment cleanup; transaction; audit log of erasure. |
| **Hardening phase** | 5.2 / 5.4 |
| **Break risk** | High if cascade is wrong — define retention policy first. |

### AUDIT-C-004 — Uninstall leaves all clinical data and media

| Field | Detail |
| --- | --- |
| **Severity** | Critical |
| **Category** | Data lifecycle / Security |
| **Affected** | `uninstall.php`, `Roles::remove`, `Capabilities::revoke_from_administrators` |
| **Explanation** | Uninstall removes custom roles and admin caps only. All `jmrs_*` tables, options (`jmrs_db_version`), transients, and uploaded files remain. |
| **Real-world risk** | Site owners assume delete removes PHI; data persists on shared hosting. |
| **Recommended fix** | Document retention clearly; optional controlled wipe with confirmation; never silent-delete without backup guidance. |
| **Hardening phase** | 5.2 / 5.7 |
| **Break risk** | High if auto-wipe is added without backup — prefer explicit opt-in wipe. |

---

# High-Priority Findings

### AUDIT-H-001 — CSV formula injection in referral export

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Security |
| **Affected** | `src/Referral/ReferralExportController.php` |
| **Explanation** | Client and free-text fields are written with `fputcsv` without neutralizing leading `=`, `+`, `-`, `@`. |
| **Real-world risk** | Spreadsheet formula execution when staff open exports. |
| **Recommended fix** | Prefix risky cells with `'` or tab; strip formula characters. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low (export consumers may see leading apostrophe). |

### AUDIT-H-002 — Referral list and export load unlimited rows

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Performance |
| **Affected** | `ReferralListController`, `ReferralRepository::find_by_filters` (`per_page = null`), `ReferralExportController` |
| **Explanation** | Admin list and CSV export request all matching referrals with no LIMIT. |
| **Real-world risk** | Timeouts / memory exhaustion at ~1k–10k referrals; hosting kill. |
| **Recommended fix** | Paginate list; stream/chunk export; select only needed columns. |
| **Hardening phase** | 5.3 |
| **Break risk** | Medium for UX of “export all” — add async/chunked export. |

### AUDIT-H-003 — Referral View N+1 query storm

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Performance |
| **Affected** | `src/Referral/ReferralViewController.php` visit loop (~444–558) |
| **Explanation** | Per visit: task generate, task load/summaries, active meds, MAR rows, display names — often 5–8 queries × N visits. |
| **Real-world risk** | Slow/timeout View pages for clients with long visit history. |
| **Recommended fix** | Batch-load tasks/MARs/meds; avoid generate-on-render; paginate visits. |
| **Hardening phase** | 5.3 |
| **Break risk** | Medium — careful regression on visit execution/MAR UI. |

### AUDIT-H-004 — AccessPolicy scoping tied to Support Worker role slug only

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Security |
| **Affected** | `src/Permissions/AccessPolicy.php` (`should_scope_to_assigned`) |
| **Explanation** | Only `jmrs_support_worker` is scoped. Custom roles with `jmrs_view_referrals` see all referrals. |
| **Real-world risk** | Privilege over-grant if roles are customized. |
| **Recommended fix** | Scope by capability/flag, not only role slug; document model. |
| **Hardening phase** | 5.2 |
| **Break risk** | Medium — may hide referrals from unintended roles. |

### AUDIT-H-005 — Support Worker referral vs visit ownership mismatch

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Security / UX |
| **Affected** | `AccessPolicy` (`assigned_to`) vs `VisitExecutionService` / MAR (`visit.assigned_user_id`) |
| **Explanation** | Referral visibility uses `assigned_to`; execution/MAR uses visit assignee. Care-team membership is not an access path. |
| **Real-world risk** | Operational confusion; workers assigned to visits but not referral cannot open View; or inverse assumptions. |
| **Recommended fix** | Document single ownership model; optionally allow view if visit-assigned **without** weakening AccessPolicy for unrelated referrals. |
| **Hardening phase** | 5.2 / 5.5 |
| **Break risk** | Medium — policy change needs explicit product decision. |

### AUDIT-H-006 — No foreign keys; wide orphan surface

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Data integrity |
| **Affected** | All `Tables.php` schemas; all child repositories |
| **Explanation** | Relationships are app-only. Deletes and manual SQL can orphan health data. |
| **Real-world risk** | Inconsistent records; hard cleanup; broken View sections. |
| **Recommended fix** | App-level cascade + integrity jobs; consider FKs where MySQL engine/hosting allows. |
| **Hardening phase** | 5.4 |
| **Break risk** | High if FKs added without data cleanup. |

### AUDIT-H-007 — `referral_number` not UNIQUE; race on generate

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Data integrity |
| **Affected** | `ReferralNumberGenerator`, `jmrs_referrals` |
| **Explanation** | Number = count(prefix)+1 without transaction/unique index. |
| **Real-world risk** | Duplicate referral numbers under concurrent creates. |
| **Recommended fix** | UNIQUE index + retry; or atomic sequence. |
| **Hardening phase** | 5.4 |
| **Break risk** | Medium if duplicates already exist. |

### AUDIT-H-008 — Plugin version claims 1.0.0 while changelog is 0.4.0

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Release management |
| **Affected** | `jm-referral-system.php` (`Version: 1.0.0`, `JMRS_VERSION`), `CHANGELOG.md` `[0.4.0]`, `Migrator::DB_VERSION` `2.13.0` |
| **Explanation** | Three version schemes disagree; WP UI shows 1.0.0 prematurely. |
| **Real-world risk** | False “production ready” signal; broken upgrade communication. |
| **Recommended fix** | Single strategy: package semver ≠ DB schema version; align header with CHANGELOG until true 1.0. |
| **Hardening phase** | 5.9 (decide in 5.2 docs) |
| **Break risk** | Low if only labels change. |

### AUDIT-H-009 — Zero automated tests

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Testing |
| **Affected** | Repository root (no PHPUnit/Pest/CI) |
| **Explanation** | No unit/integration/security automation. |
| **Real-world risk** | Regressions in permissions, MAR, schedules, migrations go unnoticed. |
| **Recommended fix** | Phase 5.6 test harness + critical path tests. |
| **Hardening phase** | 5.6 |
| **Break risk** | None. |

### AUDIT-H-010 — Operational alerts / reports fan-out cost

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Performance |
| **Affected** | `OperationalAlertService` (12× up to 200 rows), `ReportService::get_report_data` (~25 aggregates + full alerts) |
| **Explanation** | Alerts recomputed dynamically; reports embed full alert calculation. |
| **Real-world risk** | Slow Reports/Dashboard under load. |
| **Recommended fix** | Cache alert snapshot; slim KPI path; avoid double `get_alerts()`. |
| **Hardening phase** | 5.3 |
| **Break risk** | Low–medium if caching stale. |

### AUDIT-H-011 — Create referral accepts unvalidated `status`

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Security / Integrity |
| **Affected** | `ReferralController::sanitize_input`, `ReferralService::create` |
| **Explanation** | `ALLOWED_STATUSES` exists but create path does not enforce it. |
| **Real-world risk** | Invalid/unexpected statuses in DB and filters. |
| **Recommended fix** | Allowlist on create/update. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low. |

### AUDIT-H-012 — Care-plan version list loads full JSON snapshots

| Field | Detail |
| --- | --- |
| **Severity** | High |
| **Category** | Performance / Healthcare data |
| **Affected** | `ReferralCarePlanVersionRepository::get_versions_for_referral`, `ReferralViewController` |
| **Explanation** | Version list SELECT includes full LONGTEXT `snapshot` for every version. |
| **Real-world risk** | Memory/time growth; large PHI blobs in memory on View. |
| **Recommended fix** | List metadata only; load snapshot on version detail page. |
| **Hardening phase** | 5.3 / 5.4 |
| **Break risk** | Low. |

---

# Security Review

## Strengths

- Mutating controllers consistently use `admin_init` + `check_admin_referer` / `wp_nonce_url`.
- Capabilities are centralised (`Capabilities.php`) and role sync is idempotent (`Roles::register`).
- Referral-scoped modules generally call `AccessPolicy::can_view_referral` / `can_edit_referral`.
- Document download path checks uploads prefix via `realpath`.
- Report export gated by `jmrs_view_reports`; aggregates respect assigned-user constraint when scoped.
- No plugin REST/AJAX public endpoints found.
- No leftover `error_log` diagnostic calls found in current `src/`.

## Mutating / sensitive action inventory (summary)

| Action area | Controller | Nonce | Cap | AccessPolicy |
| --- | --- | --- | --- | --- |
| Create referral | `ReferralController` | Yes | CREATE | N/A |
| Update referral | `ReferralEditController` | Yes | EDIT | Edit |
| Delete referral | `ReferralListController` | Yes | DELETE | View only (see AUDIT-M-001) |
| Export referrals | `ReferralExportController` | Yes | EXPORT | Scoped query |
| Notes | `ReferralNoteController` | Yes | ADD_NOTES | View |
| Workflow stage | `ReferralViewController` | Yes | EDIT | Edit |
| Documents up/down | `ReferralDocumentController` | Yes | UPLOAD/DOWNLOAD | View |
| Assessment / care plan / review | Assessment/CarePlan/Review controllers | Yes | EDIT / MANAGE / REVIEW | Edit |
| Visits save/execute/review | `CareVisitController` | Yes | MANAGE / EXECUTE | View/Edit + service rules |
| Care team / schedules / generate | CareTeam/Schedule controllers | Yes | MANAGE_* | Edit |
| Medications | `MedicationController` | Yes | MANAGE_MEDICATIONS | Edit |
| MAR | via visit execute | Yes | ADMINISTER (service) | View + visit ownership |
| Service types / workflow | Service/Workflow controllers | Yes | MANAGE_* | N/A (global) |
| Report CSV | `ReportExportController` | Yes | VIEW_REPORTS | Via ReportService |

## Additional security findings

### AUDIT-M-001 — Delete uses `can_view_referral` not `can_edit_referral`

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security |
| **Affected** | `ReferralListController::handle_delete` |
| **Explanation** | Softened by Support Workers lacking DELETE; still inconsistent. |
| **Recommended fix** | Require edit (or delete-specific) record check. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low. |

### AUDIT-M-002 — Document upload uses view access, not edit

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security |
| **Affected** | `ReferralDocumentController::handle_upload` |
| **Explanation** | Intentional for some roles, but elevates who can attach files. |
| **Recommended fix** | Confirm product rule; prefer edit or dedicated upload policy. |
| **Hardening phase** | 5.2 |
| **Break risk** | Medium for SW if ever granted upload. |

### AUDIT-M-003 — MAR witness_user_id not validated as authorised staff

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security / Clinical integrity |
| **Affected** | `CareVisitController::sanitize_medications_input`, MAR save |
| **Explanation** | Any positive user ID may be stored as witness. |
| **Recommended fix** | Allowlist assignable/capability-bearing users. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low. |

### AUDIT-M-004 — Chart.js CDN fallback (supply chain / CSP)

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security |
| **Affected** | `ReportController::enqueue_chart_js`, `assets/vendor/README.md` |
| **Explanation** | Production may load jsDelivr if local vendor file absent. |
| **Recommended fix** | Ship pinned local `chart.umd.min.js`; block CDN in production. |
| **Hardening phase** | 5.2 / 5.7 |
| **Break risk** | Low. |

### AUDIT-M-005 — Repository layer has no access checks

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security |
| **Affected** | All repositories (`find`, `get_by_*`) |
| **Explanation** | By design; safe only if every caller enforces policy. Future endpoints could forget. |
| **Recommended fix** | Keep pattern; add service-layer tests; never call repos from templates. |
| **Hardening phase** | 5.6 |
| **Break risk** | None. |

### AUDIT-M-006 — JM roles lack `upload_files`

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Security / UX |
| **Affected** | `Roles.php`, document upload |
| **Explanation** | Non-admin JM roles may fail `media_handle_upload` depending on WP caps. |
| **Recommended fix** | Dedicated private upload that does not require Media Library caps. |
| **Hardening phase** | 5.2 |
| **Break risk** | Medium. |

### AUDIT-L-001 — Report export free-text labels

| Field | Detail |
| --- | --- |
| **Severity** | Low |
| **Category** | Security |
| **Affected** | `ReportExportController` |
| **Explanation** | Mostly aggregates; still apply formula neutralization defensively. |
| **Hardening phase** | 5.2 |
| **Break risk** | Low. |

### AUDIT-L-002 — `approved_by` / similar fields writable in map_row if callers mis-pass data

| Field | Detail |
| --- | --- |
| **Severity** | Low |
| **Category** | Security |
| **Affected** | Care plan / visit repositories |
| **Explanation** | Currently mitigated by services setting server fields. |
| **Hardening phase** | 5.4 |
| **Break risk** | Low. |

---

# Healthcare Data Risks

> Technical gaps only — not a compliance certification.

| ID | Risk | Where | Notes |
| --- | --- | --- | --- |
| AUDIT-HC-001 | Documents in public uploads | Document module | Critical — see C-001 |
| AUDIT-HC-002 | PII in email | `NotificationService::build_context` | Client name, referral meta to assignee inbox |
| AUDIT-HC-003 | PII in referral CSV | Export controller | Full client columns; formula risk |
| AUDIT-HC-004 | Operational data in report CSV | Report export | Aggregates + labels; still sensitive ops |
| AUDIT-HC-005 | Activity timeline descriptions | Activity service / View | May include clinical event text |
| AUDIT-HC-006 | Care-plan JSON snapshots | `jmrs_care_plan_versions.snapshot` | Full clinical plan copies |
| AUDIT-HC-007 | Assessment/care LONGTEXT fields | Assessments, care plans, visits | Large clinical narratives |
| AUDIT-HC-008 | Medication & MAR tables | Medication module | Health treatment data |
| AUDIT-HC-009 | Browser caching of admin pages | WP admin | Standard; consider `nocache` on View/download (download already nocache) |
| AUDIT-HC-010 | DB snapshots / host backups | Hosting | one.com backups may retain PHI after “delete” |
| AUDIT-HC-011 | Admin URLs with IDs | `referral_id`, document IDs | IDOR mitigated by caps+policy if enforced; still enumerable |
| AUDIT-HC-012 | Incomplete erasure | Delete/uninstall | See C-003, C-004 |

**Controls to consider (not claimed as implemented):** encryption at rest (host), private document store, retention/erasure policy, access logging, staff DPA training, backup encryption, export watermarking/access logs.

---

# Database and Data Integrity

## Table inventory

| Table | Purpose | Growth | Important indexes | Deletion dependency | Integrity concerns |
| --- | --- | --- | --- | --- | --- |
| `jmrs_referrals` | Root referral | Medium | status, assigned_to, service/stage FKs (logical) | Root | No UNIQUE `referral_number` |
| `jmrs_referral_activity` | Audit | High | referral_id | Orphan on referral delete | Unbounded |
| `jmrs_referral_notes` | Notes | High | referral_id | Orphan | LONGTEXT |
| `jmrs_service_types` | Lookup | Low | UNIQUE slug | App-blocked if in use | Soft FK from referrals |
| `jmrs_workflow_stages` | Lookup | Low | UNIQUE slug | App-blocked if in use | Soft FK |
| `jmrs_referral_documents` | Doc meta | Medium | referral_id, attachment_id | Orphan + media remain | No WP post FK |
| `jmrs_referral_assessments` | Assessment | 1:1 | UNIQUE referral_id | Orphan | LONGTEXT clinical fields |
| `jmrs_referral_care_plans` | Care plan | 1:1 | UNIQUE referral_id | Orphan | LONGTEXT |
| `jmrs_care_plan_versions` | Snapshots | Medium–High | UNIQUE (care_plan_id, version) | Orphan | Full JSON snapshot |
| `jmrs_care_plan_reviews` | Reviews | Medium | care_plan_id | Orphan | — |
| `jmrs_care_visits` | Visits | **Dominant** | UNIQUE generation_key; referral_id, visit_date | Orphan | NULL generation_key for manuals |
| `jmrs_care_team` | Assignments | Medium | referral_id, user_id | Orphan | No unique (referral,user) |
| `jmrs_visit_schedules` | Recurrence | Low–Med | referral_id | Orphan | days_of_week JSON in VARCHAR |
| `jmrs_visit_tasks` | Tasks | High | visit_id | Orphan | No unique (visit, name) |
| `jmrs_medications` | Med list | Medium | referral_id | Orphan | No dedup unique |
| `jmrs_medication_administrations` | MAR | High | UNIQUE (med, visit, scheduled_time) | Orphan | NULL scheduled_time duplicate gap |

**Schema version:** `Migrator::DB_VERSION = 2.13.0`  
**Migration model:** `dbDelta` recreate-all tables each upgrade — safe additive; weak for drops/renames.  
**FKs:** None.

### Additional integrity findings

| ID | Severity | Issue | Phase |
| --- | --- | --- | --- |
| AUDIT-M-007 | Medium | MAR UNIQUE allows multiple NULL `scheduled_time` | 5.4 |
| AUDIT-M-008 | Medium | Visit task generate check-then-insert race | 5.4 |
| AUDIT-M-009 | Medium | `DATE(column)` in reports vs WP timezone mismatch | 5.4 |
| AUDIT-M-010 | Medium | Care team multiple “primary” only cleared in app | 5.4 |
| AUDIT-L-003 | Low | dbDelta cannot remove obsolete columns | 5.4 |
| AUDIT-I-001 | Info | Legacy `jm_referrals` rename supported | — |

---

# Performance and Scalability

## Likely bottlenecks

| Scale | Outlook |
| --- | --- |
| **100 referrals** | Acceptable for most pages; View still heavy if many visits |
| **1,000 referrals** | List/export pain; reports slower; alerts still capped |
| **10,000 referrals** | List/export likely unusable; report aggregates multi-second |
| **100,000 visits** | Indexed per-referral OK; cross-referral reports + View-all-visits fail |

## Findings

| ID | Severity | Issue | Phase |
| --- | --- | --- | --- |
| AUDIT-H-002 | High | Unlimited list/export | 5.3 |
| AUDIT-H-003 | High | View N+1 | 5.3 |
| AUDIT-H-010 | High | Alerts/reports fan-out | 5.3 |
| AUDIT-H-012 | High | Version snapshots on list | 5.3 |
| AUDIT-M-011 | Medium | Dashboard per-row `referral_repository->find` | 5.3 |
| AUDIT-M-012 | Medium | Visit SELECT loads all LONGTEXT execution fields for every visit on View | 5.3 |
| AUDIT-L-004 | Low | Assets correctly page-scoped (reports only) — good | — |
| AUDIT-I-002 | Info | Chart CDN when vendor missing | 5.7 |

---

# PHP and WordPress Compatibility

**Target:** PHP 8.5.9 · WordPress 7.0.2 · Linux case-sensitive FS

| ID | Severity | Issue | Affected | Phase |
| --- | --- | --- | --- | --- |
| AUDIT-C-002 | Critical | Template path case | Email | 5.2 |
| AUDIT-M-013 | Medium | `$assessment['…']` when `$assessment` is null | `ReferralViewController` ~158 | 5.2 |
| AUDIT-M-014 | Medium | `map_to_form_data($assessment)` may receive null | Same | 5.2 |
| AUDIT-L-005 | Low | `extract($vars, EXTR_SKIP)` in email renderer | EmailNotificationService | 5.5 |
| AUDIT-L-006 | Low | Hidden `add_submenu_page(null, …)` — supported but watch WP changes | `Menu.php` | Informational |
| AUDIT-I-003 | Info | Recurring historical bugs (null datetime, notes foreach, days_of_week, STATUS constants) largely mitigated | Changelog | 5.6 regression tests |
| AUDIT-I-004 | Info | No `extract` of request into templates found | — | — |
| AUDIT-I-005 | Info | Composer autoload PSR-4; `vendor/` shipped in ZIP | `composer.json`, bootstrap | 5.7 |

---

# UX and Accessibility

| ID | Severity | Issue | Affected | Phase |
| --- | --- | --- | --- | --- |
| AUDIT-M-015 | Medium | Workflow stage + Add Note forms shown without UI capability gates | `templates/referrals/view.php` | 5.5 |
| AUDIT-M-016 | Medium | Monolithic ~2,400-line referral View (15 forms) | `view.php` | 5.5 |
| AUDIT-M-017 | Medium | Chart a11y gaps (no reduced-motion; weak data summary; unused `.jmrs-chart-fallback`) | Reports assets/template | 5.5 |
| AUDIT-M-018 | Medium | Support Worker ownership model confusing (referral vs visit) | AccessPolicy + visits | 5.5 |
| AUDIT-L-007 | Low | KPI severity colour-only | reports.css | 5.5 |
| AUDIT-L-008 | Low | Canvas may not print; tables do | Print CSS | 5.5 |
| AUDIT-L-009 | Low | No loading indicators on long generate/export | Schedules/Reports | 5.5 |
| AUDIT-L-010 | Low | README roles understate Support Worker execute/MAR caps | README.md | Docs |
| AUDIT-I-006 | Info | Many sections correctly gated (care plan, visits, MAR, docs) | view.php | — |
| AUDIT-I-007 | Info | Reports empty states and table+chart pattern present | reports template | — |

---

# Testing Gaps

## Current state

- No PHPUnit/Pest suite, no CI workflows, no PHPCS/PHPStan config found.
- Reliance on manual QA and production bug fixes (documented in CHANGELOG).

## Recommended testing matrix (roles × areas)

| Area | Admin | JM Admin | Manager | Coordinator | Assessor | Support Worker |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ | deny | scoped |
| Referrals CRUD | ✓ | ✓ | ✓ | limited | edit/view | view assigned only |
| Documents | ✓ | ✓ | ✓ | ✓ | ✓ | download only |
| Assessment/Care plan | ✓ | ✓ | ✓ | ✓ | ✓ | view |
| Schedules/generate | ✓ | ✓ | ✓ | ✓ | deny | deny |
| Visit execute / MAR | ✓ | ✓ | ✓ | ✓ | deny | own visits |
| Alerts | ✓ | ✓ | ✓ | ✓ | deny | deny |
| Reports/export | ✓ | ✓ | ✓ | ✓ | deny | deny |
| Settings/types/stages | ✓ | ✓ | deny* | deny | deny | deny |

\*Manager lacks settings/types/stages per Roles.php.

## Test type recommendations

| Type | Priority targets |
| --- | --- |
| **Unit** | AccessPolicy, date-range resolver, days_of_week decode, MAR date validity, CSV formula escape |
| **Integration** | Create referral → assess → plan → schedule → generate → execute → MAR; cascade delete |
| **Manual acceptance** | Full role matrix; sticky forms; empty states; print; Chart.js offline |
| **Security** | IDOR on view/download/export; nonce absence; privilege escalation; upload MIME bypass |
| **Migration** | Fresh install; upgrade from each documented DB version; Linux case paths |

### AUDIT-H-009 — covered above (no automated tests)

### AUDIT-M-019 — No staging checklist for one.com PHP 8.5 / WP 7

| Field | Detail |
| --- | --- |
| **Severity** | Medium |
| **Category** | Testing |
| **Recommended fix** | Formal CAT + staging smoke (Phase 5.8) |
| **Hardening phase** | 5.8 |
| **Break risk** | None |

---

# Versioning and Release Management

| Source | Value | Issue |
| --- | --- | --- |
| Plugin header / `JMRS_VERSION` | `1.0.0` | Premature production label |
| CHANGELOG | `[0.4.0] - 2026-08-04` | Marketing/dev semver |
| DB schema | `2.13.0` | Independent — OK if documented |
| Composer | no package version | Acceptable for WP plugin |
| README roadmap phases | Numbering differs from ROADMAP | Doc drift |
| Asset cache bust | `filemtime` / Chart `4.4.6` | Reasonable |

### Recommended versioning strategy (do not apply in this phase)

1. **Plugin semver** (`JMRS_VERSION` + header + CHANGELOG + Git tags) — product release.
2. **DB schema version** (`jmrs_db_version`) — independent monotonic string.
3. Do **not** call the product `1.0.0` until Phase 5.9 exit criteria pass.
4. Until then, keep header aligned with CHANGELOG (e.g. `0.4.x` / `0.5.0`).

---

# Recommended Hardening Plan

## Phase 5.2: Critical and High Security Fixes

| | |
| --- | --- |
| **Scope** | Private documents; email path; CSV formula escape; status allowlist; AccessPolicy clarifications; Chart.js local pin; delete/uninstall policy decisions |
| **Modules** | Documents, Notifications, Export, Permissions, Reports assets, Uninstall docs |
| **Risk** | High (security) |
| **Order** | **First** |
| **Tests** | Upload/download ACL; Linux email smoke; export spreadsheet open; role matrix smoke |
| **Progress** | **5.2.1 Private Referral Document Storage — implemented** (new private uploads + legacy batch copy; originals retained). Remaining 5.2 items still open. |

## Phase 5.3: Pagination and Performance

| | |
| --- | --- |
| **Scope** | Referral list pagination; chunked export; View visit batching/pagination; alert/report query reduction; version list without snapshots |
| **Modules** | Referral list/view, Reports, Alerts, Care plan versions |
| **Risk** | Medium |
| **Order** | Second |
| **Tests** | Load tests at 1k referrals / 10k visits; View timing |

## Phase 5.4: Data Integrity and Migration Hardening

| | |
| --- | --- |
| **Scope** | Cascade/soft-delete; UNIQUE referral_number; MAR/task uniqueness; integrity repair scripts; timezone-safe reporting dates |
| **Modules** | All repositories, Migrator |
| **Risk** | High (data) |
| **Order** | Third (after backup strategy) |
| **Tests** | Migration upgrade matrix; concurrent create; orphan scan |

## Phase 5.5: UX and Accessibility

| | |
| --- | --- |
| **Scope** | Hide unauthorized forms; View IA (anchors/sections); chart a11y; SW ownership messaging |
| **Modules** | Templates, Reports UI |
| **Risk** | Low–Medium |
| **Order** | Fourth |
| **Tests** | Keyboard/a11y spot checks; role UI matrix |

## Phase 5.6: Automated Tests

| | |
| --- | --- |
| **Scope** | PHPUnit bootstrap; AccessPolicy; MAR permissions; schedule days; CSV escape; critical services |
| **Modules** | New `tests/` |
| **Risk** | Low |
| **Order** | Parallel after 5.2 |
| **Tests** | CI on PR |

## Phase 5.7: Deployment, Backup and Recovery

| | |
| --- | --- |
| **Scope** | one.com backup/restore runbook; ZIP contents; vendor Chart.js; WP debug off; uninstall/retention docs |
| **Modules** | Ops docs, assets/vendor |
| **Risk** | Medium |
| **Order** | Before CAT |
| **Tests** | Restore drill |

## Phase 5.8: Client Acceptance Testing

| | |
| --- | --- |
| **Scope** | Full role matrix; clinical pathway; reports/print; negative security tests |
| **Modules** | All |
| **Risk** | Medium |
| **Order** | Pre-release |
| **Tests** | Signed CAT script |

## Phase 5.9: Version 1.0 Release

| | |
| --- | --- |
| **Scope** | Align versions; GitHub release; CHANGELOG; production checklist sign-off |
| **Modules** | Packaging |
| **Risk** | Low |
| **Order** | Last |
| **Tests** | Final smoke on staging clone |

---

# Production Checklist

Use before any live PHI go-live:

- [ ] Critical findings C-001–C-004 remediated and verified on **Linux** staging
- [ ] Documents not publicly URL-accessible
- [ ] Email notifications deliver correct HTML on one.com
- [ ] Referral delete/uninstall behaviour matches written retention policy
- [ ] CSV exports formula-safe
- [ ] Referral list paginated; export strategy safe at expected volume
- [ ] Referral View acceptable with representative visit volume
- [ ] Role matrix re-tested (especially Support Worker + MAR)
- [ ] `WP_DEBUG` display disabled on production; logs monitored without PHI spam
- [ ] Chart.js loaded from local vendor (no CDN dependency)
- [ ] Backup + restore drill completed
- [ ] Plugin header version matches intended release (not premature 1.0 unless exit criteria met)
- [ ] No secrets in repo; `.env` absent
- [ ] CAT signed off by client clinical/ops stakeholders

---

# Informational Notes

| ID | Note |
| --- | --- |
| AUDIT-I-008 | Architecture (Repository → Service → Controller) is coherent and should be preserved |
| AUDIT-I-009 | Reports charts/exports are page-scoped — good pattern to extend |
| AUDIT-I-010 | CHANGELOG/ROADMAP exist and are useful; keep finding IDs linked in future hardening PRs |

---

*End of Phase 5.1 audit. No production behaviour was modified by this document.*
