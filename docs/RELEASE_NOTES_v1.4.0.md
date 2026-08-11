# Release Notes — JM Referral System v1.4.0

**Release date:** 2026-08-11  
**Product version:** 1.4.0  
**Database schema:** 2.28.0 (unchanged)  
**Portal rewrite:** 1.2.2  
**Upgrade from:** v1.3.1 (DB `2.28.0`, rewrite `1.2.1`)

---

## Summary

**1.4.0** adds the **J&M Healthcare Management Dashboard**: a senior-management, read-only presentation of the live referral-to-placement pipeline using real JMRS data and the management-supplied visual design.

The existing operational Dashboard, canonical acquisition pipeline, Needs Attention rules, Supported Living occupancy logic, and database schema are unchanged.

---

## Version matrix

| Layer | Value | Notes |
| --- | --- | --- |
| Product (`Version` / `JMRS_VERSION`) | `1.4.0` | WordPress plugin semver |
| Database (`jmrs_db_version`) | `2.28.0` | **No migration** in this release |
| Portal rewrite | `1.2.2` | New `/management/` rule; flush when option lags |
| Front-end assets | `filemtime` (fallback `JMRS_VERSION`) | Management fonts/CSS/JS on management route only |

Do **not** confuse DB `2.28.0` with product `1.4.0`.

---

## Highlights

- New portal route: Management Dashboard (`/management/`)
- Six visual acquisition stages (presentation groups only — not new workflow stages)
- KPI cards with live pipeline, acquisition population, Proposed Package Value, and stage counts
- Funnel with Here now / All who reached
- Pipeline stage panels, Homes & Capacity, Ownership, Recommended Actions
- Print support

### Visual stage mapping (presentation only)

1. Local Authority Referrals → `interest_required`  
2. Appointment Set → `assessment_to_schedule`  
3. Assessment → `assessment_scheduled` + `assessment_review_required`  
4. Package Costing → `package_cost_required`  
5. Authority Consideration → `awaiting_la_decision`  
6. Placement Transition → `transition_planning` only (`care_commenced` excluded)

### Terminology

- **Proposed Package Value** — latest Package Cost `package_total` for **live** acquisition referrals only. Not revenue, annual revenue, or guaranteed income.
- Cohort conversion remains on **Reports → Acquisition**.

### Access

Same commercial gate as Pipeline Needs Attention: `VIEW_DASHBOARD` + `VIEW_REFERRALS`, and not Support Worker scoped-to-assigned. Assessor and Support Worker are denied the Management Dashboard. Surface is **read-only**.

---

## Not in this release (deferred)

Prototype-only or future items remain out of scope until explicitly approved, including: client champion, multi-attendee appointments, LA master/entity/officer, panel date, proposed/target home during costing, pre-visits, weekly hours / rates / sleep-ins, cost-floor / profitability rules, Funding Authority Performance, full Team Performance, SEED/sample data, Edit Data / JSON editor / localStorage.

---

## Upgrade notes

1. Backup WordPress DB and `uploads/jmrs-private/` before updating.
2. Replace the **same** plugin folder `jm-referral-system` (do not upload a versioned wrapper folder).
3. Activate / load admin. Confirm `jmrs_db_version` remains `2.28.0` (no schema change).
4. Confirm product header shows `1.4.0`.
5. Portal rewrite option should move to `1.2.2` and flush once when mismatched — not on every request.
6. Existing referral/pipeline data, stage history, and operational Dashboard routes are untouched. No backfill. No upgrade emails.

Direct upgrade from production **v1.3.1 / DB 2.28.0 / rewrite 1.2.1** → **v1.4.0 / DB 2.28.0 / rewrite 1.2.2**.

---

## UAT / packaging

- Management Dashboard UAT: `docs/uat/UAT_MANAGEMENT_DASHBOARD_V1_4.md`
- Packaging: `docs/PACKAGING.md` (ZIP root must be `jm-referral-system/`)
- Checklist: `docs/RELEASE_CHECKLIST.md`
- Changelog: `CHANGELOG.md` `[1.4.0]`

---

## Compliance wording

This release supports operational and management reporting workflows. It does **not** claim GDPR, CQC, or NHS certification.
