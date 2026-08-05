# Release Notes — JM Referral System v1.0.0

**Release date:** 2026-08-05  
**Product version:** 1.0.0  
**Database schema:** 2.17.0  

---

## Highlights

JM Referral System **1.0.0** is the first production release for J&M Healthcare referral and domiciliary care operations on WordPress.

- End-to-end referral workflow in WordPress Admin  
- Role-based access with record-level AccessPolicy  
- Private document storage and secure downloads  
- Public website intake wizard  
- Optional staff frontend portal (read-only foundation)  
- Archive-first retention and operational reporting foundation  

---

## Major features

### Clinical operations

- Referrals with unique numbering, assignment, workflow stages, notes, activity  
- Assessments and care plans (including reviews/versions)  
- Care team, schedules, visit generation and execution  
- Medication list and visit-time administration (MAR foundation)  
- Operational alerts and reporting KPIs / CSV export  

### Security and retention

- Private uploads under `uploads/jmrs-private/`  
- Capability gates and AccessPolicy  
- Archive / restore / safe permanent delete for empty records  
- CSV formula-injection hardening  

### Channels

- Public shortcode `[jmrs_public_referral_form]` with multi-step wizard  
- Staff portal at configurable `/staff-portal/` (disabled by default)  

---

## Upgrade notes

### Fresh install

Follow `docs/INSTALLATION_GUIDE.md`. Confirm `jmrs_db_version` = `2.17.0` after activation.

### From earlier development builds

1. Backup DB + `jmrs-private`  
2. Replace plugin files (include `vendor/`)  
3. Load WordPress admin to run migrations  
4. Re-test public form and portal rewrites if those features are enabled  

If upgrading from a build before public intake, expect schema migration to `2.17.0` (public-intake columns).

---

## Known limitations

See `docs/KNOWN_LIMITATIONS.md` for the authoritative list. Notable items:

- No automated test suite in v1.0  
- No background job queue / cron visit generation  
- Staff portal is read-only (editing remains in wp-admin)  
- Public form has no CAPTCHA or save-and-resume  
- Legacy Media Library cleanup is manual after migration  

---

## Documentation package

| Document | Audience |
| --- | --- |
| `docs/INSTALLATION_GUIDE.md` | Technical installers |
| `docs/ADMINISTRATOR_GUIDE.md` | Ops admins |
| `docs/STAFF_USER_GUIDE.md` | JM staff |
| `docs/PUBLIC_REFERRAL_GUIDE.md` | Site editors / public ops |
| `docs/RELEASE_CHECKLIST.md` | Go-live sign-off |
| `docs/TROUBLESHOOTING.md` / `docs/FAQ.md` | Support |

---

## GitHub release suggestion

- **Tag:** `v1.0.0`  
- **Title:** `JM Referral System 1.0.0`  
- **Description:** Paste this file’s Highlights + Upgrade notes + link to `docs/KNOWN_LIMITATIONS.md`  
- Attach the production ZIP built per packaging rules in the README  

---

## Thank you

This release consolidates Phases 1–6.2A foundation work into a deployable production package for J&M Healthcare.
