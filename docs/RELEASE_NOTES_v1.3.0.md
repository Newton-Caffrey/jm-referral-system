# Release Notes — JM Referral System v1.3.0

**Release date:** 2026-08-11  
**Product version:** 1.3.0  
**Database schema:** 2.28.0  
**Portal rewrite:** 1.2.1  
**Upgrade from:** v1.2.0 (DB `2.21.0`)

---

## Summary

**1.3.0** delivers the **Referral Acquisition Pipeline** (Phase 3): structured stages from interest through assessment, package cost, Local Authority decision, transition planning, and care commencement, plus operational Pipeline Dashboard attention and Acquisition reporting on the existing Reports module.

Builds on Supported Living and clinical operations shipped in **v1.2.0**.

---

## Version matrix

| Layer | Value | Notes |
| --- | --- | --- |
| Product (`Version` / `JMRS_VERSION`) | `1.3.0` | WordPress plugin semver |
| Database (`jmrs_db_version`) | `2.28.0` | Additive migrations from `2.21.0` |
| Portal rewrite | `1.2.1` | Unchanged for this release |
| Front-end assets | `filemtime` (fallback `JMRS_VERSION`) | Existing convention |

Do **not** confuse DB `2.28.0` with product `1.3.0`.

---

## Highlights

- Canonical acquisition pipeline with stage history
- Express Interest (+ email)
- Assessment scheduling and outcome review
- Package Cost prepare/send (+ private attachment email)
- Local Authority Decision and Not Proceeding
- Transition Planning (Supported Living / Own Home) and Care Commencement
- Pipeline Dashboard & Needs Attention; optional internal targets
- Acquisition Pipeline report + CSV (structured Phase 3 cohort identity)

After care commencement, existing care plans, care teams, schedules, visits, and MAR continue as before.

---

## Upgrade notes

1. Backup WordPress DB and `uploads/jmrs-private/` before updating.
2. Replace the **same** plugin folder `jm-referral-system` (do not upload a versioned wrapper folder).
3. Activate / load admin so `Migrator` runs. Confirm `jmrs_db_version` = `2.28.0`.
4. Product header shows `1.3.0`. Portal rewrite stays `1.2.1`.

Direct upgrade from production **v1.2.0 / DB 2.21.0** applies schema and idempotent canonical stage seeding for `2.22.0`–`2.28.0` in one pass. Legacy referrals are **not** remapped; no migration emails or fake milestones.

Fresh install activates at DB `2.28.0` with current tables and canonical stages.

---

## UAT / packaging

- Phase 3 UAT: `docs/uat/UAT_PHASE_3.md`
- Packaging: `docs/PACKAGING.md` (ZIP root must be `jm-referral-system/`)
- Checklist: `docs/RELEASE_CHECKLIST.md`

---

## Compliance wording

This release supports operational and audit-friendly care workflows. It does **not** claim GDPR, CQC, or NHS certification.
