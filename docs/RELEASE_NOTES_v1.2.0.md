# Release Notes — JM Referral System v1.2.0

**Release date:** 2026-08-09  
**Product version:** 1.2.0  
**Database schema:** 2.21.0  
**Portal rewrite version:** 1.2.1 (independent; auto-flushes when mismatched)

---

## Highlights

**1.2.0** delivers **Supported Living** estate operations and reporting on top of the v1.0.0 clinical platform, and includes staff-portal clinical/UX work completed after 1.0.0.

- Homes, bedrooms, capacity, occupancy, vacancy  
- Place / transfer / end placement with history  
- Care Setting and Client's Own Home  
- Home operational dashboard  
- Service location + historical visit snapshots  
- Supported Living, vacancy, movement, and visit care-delivery reports  

---

## Version map

| Layer | Value | Notes |
| --- | --- | --- |
| Product (`Version` / `JMRS_VERSION`) | `1.2.0` | WordPress plugin semver |
| Database (`Migrator::DB_VERSION`) | `2.21.0` | Independent of product semver |
| Portal rewrites | `1.2.1` | Flushed when option lags |
| Front-end assets | `filemtime` (fallback `JMRS_VERSION`) | Existing convention |

Do **not** confuse DB `2.21.0` with product `1.2.0`.

---

## Upgrade notes

1. Take DB + `uploads` (including `jmrs-private`) backup.  
2. Deploy plugin files (include `vendor/`).  
3. Load WP Admin so `Migrator::maybe_migrate()` can reach `2.21.0`.  
4. Confirm roles/capabilities sync (Homes / Occupancies / Reports).  
5. Visit Staff Portal once to allow rewrite flush if needed.  
6. Run `docs/uat/UAT_SUPPORTED_LIVING_V1_2.md` on staging before production.

### Fresh install

Confirm `jmrs_db_version` = `2.21.0` after activation. Product header shows `1.2.0`.

### Rollback

Restore previous plugin ZIP + DB + uploads backup. Clear caches if CDN/page cache is used.

---

## Documentation

- `docs/SUPPORTED_LIVING.md`  
- `docs/RELEASE_CHECKLIST.md` (v1.2 section)  
- `docs/uat/UAT_SUPPORTED_LIVING_V1_2.md`  
- `docs/uat/UAT_SUPPORTED_LIVING_REPORTING.md`  
- `docs/KNOWN_LIMITATIONS.md`  

---

## Known limitations (summary)

1. Movement reports use activity **recorded-at** time  
2. No home-specific historical movement filter (no structured Home IDs on activity)  
3. Vacant Since = latest recorded occupancy end  
4. Historical occupancy trend chart deferred  
5. Legacy executed visits without snapshots → Location Not Recorded  
6. Support Workers lack estate-wide Homes/Reports access  
7. Schedules do not store independent service-location overrides  

Full list: `docs/KNOWN_LIMITATIONS.md`.
