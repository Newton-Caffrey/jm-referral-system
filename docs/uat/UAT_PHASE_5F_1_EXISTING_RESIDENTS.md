# UAT — Phase 5F.1 Add Existing Residents

**Product:** 1.5.0 (unreleased changes)
**Database:** 2.33.0 → **2.34.0** (additive migration)
**Portal rewrite:** 1.2.11
**Branch:** `feature/5f-existing-residents` (on top of `feature/5e-referral-form-upload`)
**Requested by:** J&M Healthcare — “Add Existing Resident / Resident Migration” (October 2026)

**Overall result:** **DEVELOPMENT VERIFICATION PASS (113/113 automated checks) · staging UAT not run**

**Verification date:** 2026-10-29

**Method:** A throwaway WordPress site on PHP 8.3 was rebuilt from nothing and driven over HTTP as Referral Manager, Care Coordinator, Assessor, Support Worker, WordPress administrator and signed out. Two made-up properties (Oak House: Room 1–3; Elm Court: Flat A–B), one Local Authority and one service type were set up. All people are invented. The earlier Phase 5E.1 suite (81 checks) was re-run on the same build with no failures.

**Environment limits. Read before relying on this record.**

- SQLite, not MySQL. Named locks (`GET_LOCK`) and row locks (`FOR UPDATE`) are stood in for by the test-site database layer, so concurrency between two staff importing at the same moment was **not** tested on InnoDB. The all-or-nothing rollback *was* exercised (see C7).
- `TIMESTAMPDIFF`, used by the existing acquisition timing figures, is not available on SQLite. Those three figures error on the test site only; this is unrelated to this phase.
- Email was not available. This phase sends no email; the code path was reviewed for that.
- Screens checked by screenshot at desktop and phone width. No assistive-technology pass.

## Results

| # | Area | Checks | Result |
| --- | --- | --- | --- |
| C1 | Access: Manager and WP administrator allowed; Coordinator, Assessor, Support Worker and signed-out visitors blocked on all four routes; nav link only for permitted users; overview lists properties and free bedrooms | 18 | PASS |
| C2 | Single form validation: required fields, future dates, missing bedroom, Own Home address, bad money/hours, LA required, typed values kept, nothing saved on error, all errors shown at once | 7 | PASS |
| C3 | Single add (Supported Living): Care Started with real dates, `in_progress`, placement in chosen bedroom from admission date, funding saved, exactly one timeline entry and one stage-history row, no interest/assessment/costing/LA decision rows, record page panel and notice | 14 | PASS |
| C4 | Duplicate person refused; occupied bedroom refused and no longer offered; Own Home add with no placement and postcode tidied | 5 | PASS |
| C5 | Templates: CSV download, Excel template served, untouched template imports nothing | 3 | PASS |
| C6 | Excel import of 4 residents: preview saves nothing, Excel and typed UK dates, owner by email, service by name, room-move date, unmatched authority kept as written with a note, NHS CHC wording, leading zeros kept, one shared import reference, pending check cleared, single-use confirmation | 19 | PASS |
| C7 | Error file (9 rows, 8 wrong): unknown property/bedroom (lists real ones), impossible dates, same bedroom twice, occupied bedroom, unknown funding type and care setting, duplicate person, property on Own Home row, unknown column reported, no confirm offered, nothing saved | 15 | PASS |
| C8 | All-or-nothing: bedroom filled between preview and confirm → whole import refused, valid rows not saved | 4 | PASS |
| C9 | File safety: fake .xlsx, Excel named .csv, other types, > 1 MB, empty, missing columns, > 500 rows, bad nonces, HTML/formula text escaped | 10 | PASS |
| C10 | Reporting: acquisition cohort/export and referrals-received exclude existing residents (also for a scoped user); caseload and occupancy include them; pipeline outcome counts exclude them; dashboards, client list, wp-admin list and reports render; no plugin PHP warnings or database errors | 18 | PASS |

## Staging checklist (to run on MySQL before the client uses it)

Back up the staging database first. The migration runs automatically on the first admin page load.

| # | Step | Expected | Result |
| --- | --- | --- | --- |
| S1 | Load wp-admin after installing the build | Settings → database version `2.34.0`; no errors | |
| S2 | Log in to the portal as a Referral Manager | **Add Existing Residents** in the menu | |
| S3 | Add one Supported Living resident with funding | Record opens at Care Started; Existing resident panel shows dates and funding; bedroom shows occupied under Vacancies / Occupancy | |
| S4 | Add one Own Home resident | Record at Care Started; no placement | |
| S5 | Download the Excel template, fill 3–5 rows with the client's real property and bedroom names (made-up people), upload | All rows Ready; confirm; result page lists them | |
| S6 | Upload a copy with one deliberate mistake | Row error shown; nothing saved | |
| S7 | Reports → Acquisition for this month | Figures unchanged by the residents added above | |
| S8 | Management dashboard | Acquisition KPI unchanged; occupancy reflects new residents | |
| S9 | Archive the test residents | Archived; bedrooms still occupied until placements are ended (end them from Occupancy) | |

## Open questions for J&M

- Funding is recorded as one current arrangement per person. Changes to funding over time (renewals, uplifts) are not tracked yet.
- Care plans, risk assessments and documents are added per person after import. Bulk document upload is not included.
