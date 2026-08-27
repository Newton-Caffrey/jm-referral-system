# Release Checklist — JM Referral System

**Current target release:** `1.5.0` (artifact packaging — production not deployed)
**Product version:** `1.5.0` (`jm-referral-system.php` / `JMRS_VERSION`)
**Database schema:** `2.29.0` (`Migrator::DB_VERSION` / option `jmrs_db_version`)
**Portal rewrite:** `1.2.7` (`PortalRouter::REWRITE_VERSION`)
**Release notes:** `CHANGELOG.md` `[1.5.0]`; `docs/RELEASE_NOTES_v1.5.0.md`
**RC preparation UAT:** `docs/uat/UAT_PHASE_4J_0_1_RELEASE_CANDIDATE_PREPARATION.md` (**PASS** 2026-08-27)
**Regression evidence:** Phases **4B–4I** focused UAT accepted as cumulative evidence; full duplicate lifecycle replay **not** required for packaging
**Final staging smoke:** required before production (see `docs/RELEASE_NOTES_v1.5.0.md` §7)

Use this checklist before promoting a build to production. Tick items on staging with synthetic or anonymised data first. **Do not mark items passed unless manually confirmed.**

JMRS supports role-based access, audit activity, retention workflows, and private-document handling. Hosting, TLS, backups, SMTP, and target-server document protection remain operator responsibilities. This checklist does not constitute GDPR, CQC, NHS, or medical-device certification.

---

## PRE-REGRESSION (Phase 4J.0.1 preparation)

- [ ] Plugin header `Version` = `1.5.0`
- [ ] `JMRS_VERSION` = `1.5.0`
- [ ] `Requires at least: 6.0` and `Requires PHP: 8.0` present in plugin header
- [ ] `composer.json` requires `php >=8.0`
- [ ] README / `readme.txt` / CHANGELOG `[1.5.0]` / `docs/RELEASE_NOTES_v1.5.0.md` agree on **1.5.0 / 2.29.0 / 1.2.7**
- [ ] DB version constant remains `2.29.0` (no new migration in this preparation)
- [ ] Portal rewrite constant remains `1.2.7`
- [ ] Historical `v1.4.0` release notes / UAT records remain historically accurate
- [ ] Working tree clean after RC preparation UAT (before packaging commit)
- [ ] No secrets / credentials in package tree
- [ ] No unexpected migrations or schema edits in the RC diff
- [ ] Dependency audit: no new third-party Composer packages; Chart.js / Google Fonts CDN behaviour documented honestly

---

## PHASE 4J.1 / CUMULATIVE REGRESSION EVIDENCE

Feature-by-feature UAT from Phases **4B–4I** is **accepted** as cumulative release evidence. A full duplicate lifecycle replay was **not** performed for packaging. Release-preparation UAT (**4J.0.1**) passed **2026-08-27**.

Remaining packaging gates:

- [ ] Final staging smoke using exact `jm-referral-system-1.5.0.zip` (see release notes §7)
- [ ] Production not deployed until smoke signed off

Previously code-reviewed-only paths (Package email, LA Declined/Not Proceeding, status-change email, Own Home commence, meeting Batch 4 leftovers) remain documented limitations / smoke risks — do not claim they were fully re-executed in a duplicate lifecycle.
---

## PACKAGE

- [ ] Planned artifact name: `jm-referral-system-1.5.0.zip`
- [ ] Exact root directory: `jm-referral-system/`
- [ ] `vendor/` included (`vendor/autoload.php` mandatory)
- [ ] `_reference/`, `docs/uat/`, `docs/audits/`, IDE/OS junk, dumps, logs, prior ZIPs excluded
- [ ] Extraction test (single top-level folder)
- [ ] File count recorded
- [ ] ZIP size recorded
- [ ] SHA-256 recorded
- [ ] Malware / secret scan

**Do not create the ZIP until packaging is explicitly requested after regression.**

---

## DEPLOYMENT

- [ ] Site backup
- [ ] Database backup
- [ ] Current production plugin directory / ZIP backup
- [ ] Maintenance-window decision
- [ ] Replace plugin files **without uninstall**
- [ ] Migration verification (`jmrs_db_version` = `2.29.0`)
- [ ] Rewrite verification (`1.2.7` / portal routes)
- [ ] Role / capability verification
- [ ] SMTP verification
- [ ] Private-document verification
- [ ] Post-deploy smoke tests
- [ ] Rollback decision point documented

---

## Historical — v1.4.0 Management Dashboard release

Completed for production **v1.4.0**. Retained for reference. Do not retarget these ticks to 1.5.0.

- [x] Plugin header Version = `1.4.0` (historical)
- [x] DB version remained `2.28.0` (no migration in 1.4.0)
- [x] Portal rewrite = `1.2.2`
- [x] Release notes: `docs/RELEASE_NOTES_v1.4.0.md`

---

## Historical — v1.3.1 Public referral Local Authority wording patch

- [x] Plugin header Version = `1.3.1` (historical)
- [x] DB version remains `2.28.0` (no migration)
- [x] Portal rewrite remains `1.2.1`

---

## Historical — v1.3.0 Acquisition Pipeline release gate

Completed for production v1.3.0. Retained for reference.

- [x] Plugin header Version = `1.3.0` (historical)
- [x] DB version = `2.28.0`
- [x] Phase 3 UAT (`docs/uat/UAT_PHASE_3.md`)
- [x] Direct upgrade from v1.2.0 / DB `2.21.0` → `2.28.0`

---

## Smoke checks (reusable)

Prefer the **1.5.0 PRE-REGRESSION / 4J.1 / PACKAGE / DEPLOYMENT** gates above for the upcoming release. The following remain useful operational smokes.

### Pre-deploy backups and environment

- [ ] Backup WordPress database
- [ ] Backup `wp-content/uploads/` including `uploads/jmrs-private/`
- [ ] Confirm release ZIP includes `vendor/`
- [ ] Confirm ZIP excludes `.git`, `node_modules`, OS junk, editor-only configs, `uat-evidence/`, `_reference/`
- [ ] Confirm SMTP / `wp_mail` works on the target host
- [ ] Confirm PHP version meets requirements (8.0+, 8.1+ preferred)
- [ ] Confirm product header / `JMRS_VERSION` / README / CHANGELOG / `readme.txt` all read **1.5.0** for the candidate build
- [ ] Read `docs/KNOWN_LIMITATIONS.md` and `docs/RELEASE_NOTES_v1.5.0.md`
- [ ] After deploying CSS/JS changes: purge host/page/CDN caches so `filemtime` asset URLs are fetched fresh

### Fresh install

- [ ] Upload/activate plugin
- [ ] Activation creates/reaches tables (`jmrs_db_version` = `2.29.0`)
- [ ] JM roles appear
- [ ] Administrator receives JM capabilities
- [ ] Private document directory created under `uploads/jmrs-private/` with protection files
- [ ] Default workflow stages seeded when empty
- [ ] Dashboard, Referrals list, Settings, Supported Living Homes, Management Dashboard load without PHP errors
- [ ] Rewrite flush on activation does not break the site

### Upgrade path (production-like)

- [ ] From **v1.4.0** / DB **2.28.0**: backup → replace files → activate/load admin
- [ ] `Migrator::maybe_migrate()` reaches `2.29.0`
- [ ] Meeting tables and Champion / Transition Lead columns present
- [ ] Existing referrals, visits, documents, schedules, package/LA/occupancy rows remain intact
- [ ] Portal rewrite reaches `1.2.7`
- [ ] Re-test one Support Worker login after upgrade
- [ ] Re-test one Care Coordinator Homes/Occupancy path after upgrade

### Rollback

- [ ] Restore previous plugin ZIP
- [ ] Restore pre-upgrade database backup (preferred when schema advanced)
- [ ] Restore `uploads` / `jmrs-private` if needed
- [ ] Clear caches; flush permalinks if required
- [ ] Confirm `jmrs_db_version` matches restored codebase expectations
