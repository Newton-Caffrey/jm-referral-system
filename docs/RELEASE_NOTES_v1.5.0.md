# Release Notes — JM Referral System v1.5.0

**Product version:** 1.5.0  
**Database schema:** 2.29.0  
**Portal rewrite:** 1.2.7  
**Git tag:** `v1.5.0`  
**Artifact:** `jm-referral-system-1.5.0.zip`  
**Production status:** **Not yet deployed** — release artifact packaging complete; final staging smoke required before production  
**Upgrade from:** production **v1.4.0** (DB `2.28.0`, rewrite `1.2.2`)

---

## 1. Release identity

| Layer | Value |
| --- | --- |
| Product (`Version` / `JMRS_VERSION`) | `1.5.0` |
| Database (`jmrs_db_version`) | `2.29.0` |
| Portal rewrite | `1.2.7` |
| Minimum WordPress | 6.0 |
| Minimum PHP | 8.0 |

JMRS supports role-based access, audit activity, retention workflows, and private-document handling. Hosting configuration, TLS, backups and disaster recovery, SMTP, and target-server document protection remain the operator's responsibility. This release does **not** claim GDPR, CQC, NHS, or medical-device certification, and does not guarantee security or availability.

---

## 2. Upgrade summary

1. Back up WordPress site files.
2. Back up the database.
3. Back up the current JMRS plugin directory or production ZIP.
4. **Do not uninstall** the existing plugin to upgrade.
5. Replace plugin files with the `1.5.0` package (root folder must be exactly `jm-referral-system/`; include `vendor/autoload.php`).
6. Activate or load the plugin so migrations and rewrite checks run.
7. Verify `jmrs_db_version` = **2.29.0**.
8. Verify meeting tables and responsibility columns exist.
9. Verify portal rewrite option / behaviour for **1.2.7**.
10. Verify staff portal routes and Management Dashboard.
11. Verify roles and capabilities.
12. Verify SMTP / `wp_mail`.
13. Verify private-document download on the target host.
14. Run post-upgrade smoke tests.

**Migration:** additive `2.28.0` → `2.29.0` (meetings, meeting attendees, Champion / Transition Lead columns). No fabricated backfill.

**Rollback:** preferably restore previous plugin files **and** the pre-upgrade database backup. A code-only rollback does **not** fully reverse the DB migration.

---

## 3. Feature summary (since v1.4.0)

- Referral meetings, internal attendees, external participants
- Owner, Champion, and Transition Lead responsibilities
- Management Operations dashboard (scoped real aggregates)
- Assessment hardening and Operations assessment metrics
- Package Costing hardening and Operations package metrics
- Local Authority Decision hardening and Operations decision metrics
- Transition planning readiness panel
- Occupancy / Supported Living placement integration for transition
- Care-commencement milestone (record-once)
- Management Dashboard visual polish (Operations spacing, tables, badges, activity hierarchy)

Historical **v1.4.0** release notes remain the record of the original Management Dashboard ship (DB `2.28.0`, rewrite `1.2.2`).

---

## 4. Operational behaviour

- Acquisition workflow ends at pipeline stage `care_commenced`
- Referral status remains / becomes `in_progress` after care commencement (not auto-`completed`)
- Place Resident creates occupancy and does **not** itself advance the acquisition pipeline
- Care commencement is **record-once**
- Completed assessments, sent package costs, recorded LA decisions, and recorded care commencement are **terminal / read-only** for those workflows
- Archive remains the supported operational retention path

---

## 5. Known limitations and product decisions

- Assessor visibility of package information when a referral is visible: **existing product behaviour — requires future JM confirmation**
- Assessor visibility of LA-decision information (including notes) when a referral is visible: **existing product behaviour — requires future JM confirmation**
- Assessor visibility of transition / placement information when a referral is visible: **existing product behaviour — requires future JM confirmation**
- Proposed Package Value does not carry billing-frequency semantics
- Authority analytics rely on free-text authority data
- Some Management Dashboard boards use documented row limits
- Management Dashboard distinguishes occupied-now from confirmed future move-ins
- Operational Homes pages treat future-dated active occupancy as unavailable (**known product semantic**)
- SMTP / `wp_mail` is an environment dependency
- Private-document protection must be verified on the target hosting server (Apache `.htaccess` may not apply on nginx)
- Permanent uninstall purge is **not** the supported production data-retention workflow; default uninstall preserves operational data; opt-in wipe is administrative/development and is not documented as complete purge coverage
- No proposed-home reservation, transition checklist, or target-bedroom reservation workflow
- No correction / reopen workflow for terminal commercial records

### Front-end dependencies

- Management / reporting Chart.js: loads local `assets/vendor/chart.umd.min.js` when present; the packaged tree currently lacks that file and falls back to jsDelivr Chart.js 4.4.6. Verify chart availability during final staging smoke. Vendoring an approved Chart.js build remains a deferred hardening option (requires version, licence, provenance, and approval).
- Management Dashboard Google Fonts enqueue requires network access where that route loads fonts.

---

## 6. Regression and release evidence

**Accepted cumulative evidence:** Feature-by-feature focused UAT from Phases **4B–4I** is accepted as cumulative release regression evidence. Release-preparation UAT (Phase **4J.0.1**) passed **2026-08-27**. A full duplicate end-to-end lifecycle replay was **not** performed for packaging.

**Still required before production:** final staging smoke using the exact `jm-referral-system-1.5.0.zip` (activation/upgrade sanity, portal/dashboard opens, existing data present, SMTP test, private-document authorised/unauthorised checks, no PHP/JS console errors, mobile dashboard smoke). Production has **not** been deployed.

**Known limitations preserved:** Assessor visibility decisions require future JM confirmation; Own Home path was code-reviewed but not manually exercised during Phase 4H; Package and LA email paths depend on SMTP/`wp_mail`; private-document protection must be verified on the target host; Chart.js currently has a CDN fallback; Homes and Management use different future-occupancy presentation semantics; PPV has no billing-frequency model; terminal commercial records have no correction/reopen workflow.

This release does **not** claim GDPR, CQC, NHS, or medical-device certification.

---

## 7. Final staging smoke checklist

- [ ] Upload the exact ZIP to staging
- [ ] Replace without uninstalling
- [ ] Activate successfully
- [ ] Plugins screen shows 1.5.0
- [ ] DB remains 2.29.0
- [ ] Rewrite remains 1.2.7
- [ ] Staff Portal opens
- [ ] One existing referral opens
- [ ] Management Dashboard opens
- [ ] Operations tab opens
- [ ] Meetings opens
- [ ] Homes and Capacity opens
- [ ] Existing data remains present
- [ ] Page refresh creates no activity
- [ ] SMTP test email succeeds
- [ ] Authorised private-document download succeeds
- [ ] Unauthorised private-document access is denied
- [ ] No PHP warning or JavaScript console error
- [ ] Mobile dashboard smoke test passes

Do **not** require another complete lifecycle replay.

---

## Related documents

- `CHANGELOG.md` `[1.5.0]`
- `docs/RELEASE_CHECKLIST.md`
- `docs/PACKAGING.md`
- `docs/INSTALLATION_GUIDE.md`
- `docs/KNOWN_LIMITATIONS.md`
- `docs/uat/UAT_PHASE_4J_0_1_RELEASE_CANDIDATE_PREPARATION.md`
- `docs/uat/UAT_PHASE_4J_1_FULL_RELEASE_CANDIDATE_REGRESSION.md`
- Historical: `docs/RELEASE_NOTES_v1.4.0.md`
