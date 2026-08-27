# UAT — Phase 4J.1 Release Evidence Record (1.5.0)

**Release-candidate / packaging baseline commit:** `fed12f2b381bfe6de24a4be732253af9376a1d70`  
**Product:** 1.5.0  
**Database:** 2.29.0  
**Portal rewrite:** 1.2.7  
**Git tag:** `v1.5.0`  
**Artifact:** `jm-referral-system-1.5.0.zip`  
**Production status:** not deployed

---

## Evidence decision

| Item | Record |
| --- | --- |
| Feature-by-feature UAT (Phases 4B–4I) | **Accepted as cumulative release regression evidence** |
| Release-preparation UAT (Phase 4J.0.1) | **PASS** 2026-08-27 |
| Full duplicate end-to-end lifecycle replay | **Not performed** (not required for packaging under this release decision) |
| Final staging smoke using production ZIP | **Required before production** (see `docs/RELEASE_NOTES_v1.5.0.md` §7) |
| Production deployment | **Not performed** |

---

## Known limitations preserved

- Assessor visibility decisions require future JM confirmation
- Own Home path was code reviewed but not manually exercised during Phase 4H
- Package and LA email paths depend on SMTP/`wp_mail`
- Private-document protection must be verified on the target host
- Chart.js currently has a CDN fallback
- Homes and Management use different future-occupancy presentation semantics
- PPV has no billing-frequency model
- Terminal commercial records have no correction/reopen workflow

This record does **not** claim GDPR, CQC, NHS, or security certification.

---

## Final staging smoke checklist

Do **not** mark PASS until executed with the exact ZIP.

| # | Check | Result |
| --- | --- | --- |
| 1 | Upload the exact ZIP to staging | |
| 2 | Replace without uninstalling | |
| 3 | Activate successfully | |
| 4 | Plugins screen shows 1.5.0 | |
| 5 | DB remains 2.29.0 | |
| 6 | Rewrite remains 1.2.7 | |
| 7 | Staff Portal opens | |
| 8 | One existing referral opens | |
| 9 | Management Dashboard opens | |
| 10 | Operations tab opens | |
| 11 | Meetings opens | |
| 12 | Homes and Capacity opens | |
| 13 | Existing data remains present | |
| 14 | Page refresh creates no activity | |
| 15 | SMTP test email succeeds | |
| 16 | Authorised private-document download succeeds | |
| 17 | Unauthorised private-document access is denied | |
| 18 | No PHP warning or JavaScript console error | |
| 19 | Mobile dashboard smoke test passes | |

Do **not** require another complete lifecycle replay.

---

## Full lifecycle checklist archive

The detailed RC-A…RC-F / role-matrix / IDOR checklists prepared earlier in Phase 4J.1 remain available as an optional deep-regression template if JM later requires a full replay. They are **not** marked PASS for this packaging decision.

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Release evidence acceptance | Cumulative 4B–4I + 4J.0.1 | 2026-08-27 | **Accepted for packaging** |
| Final staging smoke | | | _pending_ |
| Production deploy | | | **Not performed** |

**Packaging verdict:** cumulative evidence accepted; artifact may be built; production blocked until final staging smoke.
