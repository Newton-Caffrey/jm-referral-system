# UAT Checklist — Supported Living Reporting (Phase 2G / v1.2)

Manual Pass/Fail checklist for WP Admin **J&M Referrals → Reports** after Phases 2G.1–2G.5.

**Do not invent evidence.** Record results during testing.

| Field | Value |
| --- | --- |
| Tester | |
| Environment | |
| Date | |
| DB version | `2.21.0` (expected) |
| Build / branch | |

**Roles:** JM Administrator / Referral Manager / Care Coordinator expect report access. Assessor / Support Worker expect no report access unless explicitly granted.

---

## A. Page structure & filters

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-01 | Open Reports | Sections ordered: Snapshot → Vacancy → Movements → Visits / existing KPIs → Trends | | |
| SL-REP-02 | Change date range | Snapshot + Vacancy unchanged; Movements + Visits + existing period reports change | | |
| SL-REP-03 | Vacancy Home filter | Vacancy section only; Movements + Visits unchanged | | |
| SL-REP-04 | Visit Care Delivery / Visit Home | Visit KPIs + Visit Analytics (+ visit-linked Task / visit Staff metrics) only | | |
| SL-REP-05 | Reset Filters | Returns to default period, All Vacancy Homes, All Visit Care Delivery, All Visit Homes | | |
| SL-REP-06 | Narrow viewport | No horizontal page overflow; tables scroll; filters wrap; long names wrap | | |

---

## B. Estate & care-setting snapshot

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-10 | Capacity / Occupied / Vacant / Occupancy % | Match Homes List, Vacancy Board, Home Dashboard | | |
| SL-REP-11 | Care Setting labels | Supported Living / Client's Own Home / Not Specified (no raw keys) | | |
| SL-REP-12 | Awaiting Placement | SL + no active occupancy counted; Occupied does not include them | | |
| SL-REP-13 | Place awaiting client | Awaiting decreases; Occupied increases | | |
| SL-REP-14 | No active homes | Empty copy: “No active Supported Living homes have been added.” | | |
| SL-REP-15 | Completed/cancelled records | Excluded from current care-setting counts | | |

---

## C. Vacancy

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-20 | Never occupied bedroom | Vacant Since = Never occupied | | |
| SL-REP-21 | Previously occupied vacant | Vacant Since = latest move_out_date | | |
| SL-REP-22 | Occupied bedroom | Not listed | | |
| SL-REP-23 | Inactive bedroom/home | Not in vacancy/capacity | | |
| SL-REP-24 | Vacant KPI vs table | Vacant count matches vacancy row count (scoped home) | | |
| SL-REP-25 | No vacancies | “No vacant bedrooms are currently available.” (or home-scoped variant) | | |
| SL-REP-26 | Vacancy CSV + Rosewood | Only Rosewood vacancies; no clinical/address narrative | | |

---

## D. Placement movements

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-30 | Place → Transfer → End in period | New=1, Transfer=1, Ended=1 (no double count) | | |
| SL-REP-31 | Backdated move dates | Reporting still uses activity.created_at | | |
| SL-REP-32 | Completed/archived referral | Historical movement event still listed for period | | |
| SL-REP-33 | >100 events | UI shows latest 100 + export note; CSV has full set | | |
| SL-REP-34 | Empty period | “No placement movements were recorded during this period.” | | |
| SL-REP-35 | Movements CSV | Rows match KPI counts for same period/filters | | |

---

## E. Visit care-delivery analytics

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-40 | No Visit filters | Visit numbers match pre-filter / All+All behaviour | | |
| SL-REP-41 | Client A: Oak execute → Rosewood transfer → Rosewood execute → Willow transfer | Oak filter = Oak visit; Rosewood = Rosewood visit; Willow = future only until executed | | |
| SL-REP-42 | Own Home execute then change to SL | Historical visit remains Client's Own Home | | |
| SL-REP-43 | Legacy executed, snapshot NULL | In All + Unresolved; not in specific Home | | |
| SL-REP-44 | Missed/cancelled no snapshot | All + Unresolved; not inferred to current Home | | |
| SL-REP-45 | Open SL, no occupancy | Unresolved; not in specific Home | | |
| SL-REP-46 | Filters with no matches | “No visits match the selected care-delivery filters.” | | |
| SL-REP-47 | Visit CSV | Respects date + Visit Care Delivery + Visit Home; no full street address | | |

---

## F. Own Home

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-50 | Own-Home client | Counted under Client's Own Home; not in occupancy/home residents | | |
| SL-REP-51 | Visit Own Home filter | Includes open own-home and executed own-home snapshot visits | | |

---

## G. Charts, CSV, permissions

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-60 | Chart.js blocked/unavailable | Tables/KPIs still usable; no console crash | | |
| SL-REP-61 | Zero datasets | Charts hide / empty message; no JS error | | |
| SL-REP-62 | Full Report CSV | Snapshot aggregates + vacancy summary + movement KPIs + Visit section with active Visit filters | | |
| SL-REP-63 | Privacy | No diagnoses, medications, care-plan narrative, private notes, full service addresses in SL management CSVs | | |
| SL-REP-64 | Admin/Manager/Coordinator | Can open Reports | | |
| SL-REP-65 | Assessor/Support Worker | No Reports access (unless role explicitly grants `jmrs_view_reports`) | | |

---

## H. Regression smoke

| ID | Scenario | Expected | Pass/Fail | Notes |
| --- | --- | --- | --- | --- |
| SL-REP-70 | Referral / Medication / Task / Staff / Compliance | Work with no Visit/Vacancy filters | | |
| SL-REP-71 | Public referral, edit, care setting, address, homes, occupancy, transfer, dashboard, schedule, generate, execute, review, MAR, care plan | No break from reporting polish | | |

---

## Sign-off

| Role | Name | Pass/Fail | Signature / date |
| --- | --- | --- | --- |
| Tester | | | |
| Product / ops | | | |
