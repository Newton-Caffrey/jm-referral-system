# Release Checklist — JM Referral System v1.0.0

**Product version:** `1.0.0` (`jm-referral-system.php` / `JMRS_VERSION`)  
**Database schema:** `2.17.0` (`Migrator::DB_VERSION` / option `jmrs_db_version`)  
**Date prepared:** 2026-08-05  
**Release notes:** `docs/RELEASE_NOTES_v1.0.0.md`

Use this checklist before promoting a build to production. Tick items on staging with synthetic or anonymised data first.

---

## Pre-deploy

- [ ] Backup WordPress database
- [ ] Backup `wp-content/uploads/` including `uploads/jmrs-private/`
- [ ] Confirm release ZIP includes `vendor/` (Composer autoload; `composer install --no-dev`)
- [ ] Confirm ZIP excludes `.git`, `node_modules`, OS junk, editor-only configs
- [ ] Confirm SMTP / `wp_mail` works on the target host
- [ ] Confirm PHP version meets requirements (8.0+, 8.1+ preferred)
- [ ] Confirm product header / `JMRS_VERSION` / README / CHANGELOG all read `1.0.0`
- [ ] Read `docs/KNOWN_LIMITATIONS.md` and `docs/RELEASE_NOTES_v1.0.0.md`

---

## Fresh install

- [ ] Upload/activate plugin
- [ ] Activation creates tables (`jmrs_db_version` = `2.17.0`)
- [ ] JM roles appear (JM Administrator, Referral Manager, Care Coordinator, Assessor, Support Worker)
- [ ] Administrator receives JM capabilities
- [ ] Private document directory created under `uploads/jmrs-private/` with protection files
- [ ] Default workflow stages seeded when empty
- [ ] Dashboard, Referrals list, Settings load without PHP errors
- [ ] Rewrite flush on activation does not break the site

---

## Upgrade path

- [ ] From previous install: backup → replace files → activate/load admin
- [ ] `Migrator::maybe_migrate()` reaches `2.17.0` when behind
- [ ] Public-intake columns present after upgrade from pre-`2.17.0`
- [ ] Archive columns present if upgrading from pre-`2.15.0`
- [ ] Existing referrals, visits, documents, and schedules remain intact
- [ ] Re-test one Support Worker login after upgrade

---

## Permissions & AccessPolicy

- [ ] Support Worker sees only assigned referrals
- [ ] Support Worker cannot mutate archived referrals
- [ ] Archive / restore limited to Admin / JM Admin / Referral Manager
- [ ] Permanent delete only on empty referrals with `jmrs_delete_referrals`
- [ ] Document download requires capability + AccessPolicy
- [ ] CSV export requires `jmrs_export_referrals` + nonce

---

## Clinical / operational smoke tests

- [ ] Create referral → assign → change workflow stage
- [ ] Assessment create/update
- [ ] Care plan create/update/activate; review; version snapshot view
- [ ] Care team assign
- [ ] Schedule create → generate visits (small window) → regenerate skips duplicates
- [ ] Manual visit create → complete visit (tasks/MAR) → manager review
- [ ] Medication list save; dosage/status visible in admin and portal
- [ ] Internal note add
- [ ] Edit referral “Update Referral” saves successfully

---

## Archive & retention

- [ ] Archive referral → becomes read-only; clinical mutations blocked
- [ ] Restore referral → writable again
- [ ] List Active / Archived / All filters
- [ ] Settings → Data Integrity Check shows counts only
- [ ] See `docs/DATA_RETENTION_POLICY.md`

---

## Documents

- [ ] New upload stores as private file (not public Media Library URL)
- [ ] Download via plugin link works; direct URL to private path blocked (Apache `.htaccess`)
- [ ] Legacy attachment migration batch run if old docs exist
- [ ] Confirm backup includes `jmrs-private/`

---

## Public referral intake

- [ ] Form disabled by default until Settings enable
- [ ] Shortcode page renders wizard (and no-JS fallback)
- [ ] Successful submit shows receipt / referral reference
- [ ] Ops notification + referrer confirmation emails (when SMTP works)
- [ ] Uploads (if enabled) land in private storage
- [ ] Spam controls reject empty honeypot / too-fast submits as designed
- [ ] See `docs/PUBLIC_REFERRAL_GUIDE.md`

---

## Staff portal

- [ ] Portal disabled by default
- [ ] Enable + save flushes rewrites; `/staff-portal/` loads
- [ ] Unauthenticated visitor redirected to login with return URL
- [ ] Support Worker: Dashboard + My Referrals; scoped rows
- [ ] Manager: Dashboard + Referrals; filters/pagination
- [ ] Referral view read-only; secure document download works
- [ ] Inaccessible / missing referral → generic 404
- [ ] wp-admin redirect remains **off** unless UAT passed
- [ ] With redirect on: AJAX, admin-post, downloads, exports still work
- [ ] Privacy cache headers present on portal pages
- [ ] See `docs/STAFF_PORTAL.md` / `docs/STAFF_USER_GUIDE.md`

---

## UAT (required for v1.1.0 production)

v1.1.0 must not go to production without UAT sign-off (or a documented JM Project Owner exception).

- [ ] Staging prepared per `docs/uat/UAT_PLAN.md` entry criteria
- [ ] Fictional users + seed data per `docs/uat/UAT_ROLE_MATRIX.md` and `UAT_DATA_SETUP.md`
- [ ] Mandatory scenarios completed (`docs/uat/UAT_SCENARIOS.md`)
- [ ] Defects logged; no open Critical; no open High on core workflows
- [ ] Backup/restore drill recorded
- [ ] `docs/uat/UAT_SIGN_OFF.md` completed with production recommendation
- [ ] Evidence kept privately under `uat-evidence/` (gitignored — not in ZIP)

---

## Alerts & reports

- [ ] Operational Alerts page loads; filters work
- [ ] Dashboard alert widget and reports shortcut share one alert calculation
- [ ] Reports page KPIs/charts/tables load for allowed roles
- [ ] Report CSV export (full/section) downloads
- [ ] Referral CSV export streams (large sets); formula cells neutralized

---

## Performance spot-check

- [ ] Referral list paginated (20/50/100)
- [ ] Referral View visits paginated; opening View does not create tasks
- [ ] Schedule generation of ~100 visits completes without timeout on staging
- [ ] Export of filtered referrals completes without PHP memory exhaustion

---

## Accessibility & UX

- [ ] Keyboard tab through list filters and primary actions
- [ ] Focus outline visible on controls
- [ ] Empty states readable; badges show text labels
- [ ] Portal skip link + mobile nav keyboard operable
- [ ] Confirm dialogs for archive/delete/generate/complete/review
- [ ] See `docs/UI_STYLE_GUIDE.md` and `docs/ACCESSIBILITY_REVIEW.md`

---

## Email

- [ ] Referral created / assigned / status-changed emails send (or log success) on staging
- [ ] Public intake emails send when form enabled
- [ ] Template load failure does not expose filesystem paths

---

## Cron (future)

- [ ] No plugin cron jobs in v1.0 — N/A
- [ ] Plan separately if automatic visit generation or retention purge is required later

---

## Uninstall / rollback

- [ ] Deactivate leaves data and roles intact; flushes rewrites
- [ ] Delete plugin without `JMRS_DELETE_DATA_ON_UNINSTALL` preserves tables and private files
- [ ] Opt-in wipe only after explicit `define('JMRS_DELETE_DATA_ON_UNINSTALL', true);`
- [ ] **Rollback:** restore DB + `uploads` backup + previous plugin ZIP; clear caches if any
- [ ] See `docs/BACKUP_AND_RECOVERY.md`

---

## Documentation package present

- [ ] `docs/INSTALLATION_GUIDE.md`
- [ ] `docs/ADMINISTRATOR_GUIDE.md`
- [ ] `docs/STAFF_USER_GUIDE.md`
- [ ] `docs/PUBLIC_REFERRAL_GUIDE.md`
- [ ] `docs/SECURITY.md` + root `SECURITY.md`
- [ ] `docs/BACKUP_AND_RECOVERY.md`
- [ ] `docs/TROUBLESHOOTING.md`
- [ ] `docs/FAQ.md`
- [ ] `docs/RELEASE_NOTES_v1.0.0.md`
- [ ] `docs/uat/README.md` (v1.1.0 UAT package)
- [ ] `LICENSE`, `CONTRIBUTING.md`, updated `README.md`

---

## Sign-off

For **v1.1.0**, use the formal UAT sign-off form: [`docs/uat/UAT_SIGN_OFF.md`](uat/UAT_SIGN_OFF.md).

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Technical | | | Pass / Fail |
| Operational | | | Pass / Fail |
| Go-live approved | | | Yes / No |

**Overall release stance:** Ready with recommendations (see `docs/KNOWN_LIMITATIONS.md`).
