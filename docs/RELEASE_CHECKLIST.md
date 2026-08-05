# Release Checklist — JM Referral System v1.0.0

**Product version:** `1.0.0` (`jm-referral-system.php` / `JMRS_VERSION`)  
**Database schema:** `2.16.0` (`Migrator::DB_VERSION` / option `jmrs_db_version`)  
**Date prepared:** 2026-08-05

Use this checklist before promoting a build to production (e.g. one.com). Tick items on staging with synthetic or anonymised data first.

---

## Pre-deploy

- [ ] Backup WordPress database
- [ ] Backup `wp-content/uploads/` including `uploads/jmrs-private/`
- [ ] Confirm ZIP includes `vendor/` (Composer autoload)
- [ ] Confirm SMTP / `wp_mail` works on the target host
- [ ] Confirm PHP version meets host requirements (plugin developed for modern PHP 8.x)
- [ ] Read `docs/KNOWN_LIMITATIONS.md`

---

## Fresh install

- [ ] Upload/activate plugin
- [ ] Activation creates tables (`jmrs_db_version` = `2.16.0`)
- [ ] JM roles appear (JM Administrator, Referral Manager, Care Coordinator, Assessor, Support Worker)
- [ ] Administrator receives JM capabilities
- [ ] Private document directory created under `uploads/jmrs-private/` with protection files
- [ ] Default workflow stages seeded when empty
- [ ] Dashboard, Referrals list, Settings load without PHP errors

---

## Upgrade path

- [ ] From previous install: deactivate → replace files → activate (or overwrite ZIP and load admin)
- [ ] `Migrator::maybe_migrate()` bumps schema when behind (indexes via `dbDelta` for `2.16.0`)
- [ ] Archive columns present on referrals if upgrading from pre-`2.15.0`
- [ ] Existing referrals, visits, documents, and schedules remain intact
- [ ] Re-test one Support Worker login after upgrade (caps/roles re-synced on relevant migrations)

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
- [ ] Medication list save
- [ ] Internal note add
- [ ] Edit referral “Update Referral” saves successfully (submitter preserve / Phase 5.5 fix)

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
- [ ] Confirm dialogs for archive/delete/generate/complete/review
- [ ] See `docs/UI_STYLE_GUIDE.md` and `docs/ACCESSIBILITY_REVIEW.md`

---

## Email

- [ ] Referral created / assigned / status-changed emails send (or log success) on staging
- [ ] Template load failure does not expose filesystem paths

---

## Cron (future)

- [ ] No plugin cron jobs in v1.0 — N/A
- [ ] Plan separately if automatic visit generation or retention purge is required later

---

## Uninstall / rollback

- [ ] Deactivate leaves data and roles intact
- [ ] Delete plugin without `JMRS_DELETE_DATA_ON_UNINSTALL` preserves tables and private files
- [ ] Opt-in wipe only after explicit `define('JMRS_DELETE_DATA_ON_UNINSTALL', true);`
- [ ] **Rollback:** restore DB + `uploads` backup + previous plugin ZIP; clear caches if any

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Technical | | | Pass / Fail |
| Operational | | | Pass / Fail |
| Go-live approved | | | Yes / No |

**Overall release stance:** Ready with recommendations (see `docs/KNOWN_LIMITATIONS.md`).
