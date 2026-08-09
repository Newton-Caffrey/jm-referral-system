# UAT — Supported Living Master Checklist (v1.2.0)

Manual Pass/Fail acceptance tests for **Product Phase 2 / Supported Living** shipped in plugin **1.2.0** (DB **2.21.0**).

**Do not invent evidence.** Complete Actual Result / Pass-Fail during testing. Store evidence privately under `uat-evidence/` (gitignored).

Related: [UAT_SUPPORTED_LIVING_REPORTING.md](UAT_SUPPORTED_LIVING_REPORTING.md) (reporting-focused subset) · [UAT package index](README.md)

| Field | Value |
| --- | --- |
| Tester | |
| Environment | |
| Date | |
| Product version | `1.2.0` (expected) |
| DB version | `2.21.0` (expected) |
| Portal rewrite | `1.2.1` (expected) |
| Build / branch | |

---

## How to use each case

Fill:

| Field | |
| --- | --- |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## A. Homes

### SL-UAT-01 — Create active Home

| | |
| --- | --- |
| Role | JM Administrator / Referral Manager / Care Coordinator |
| Preconditions | User can manage Homes |
| Steps | Create a new Home with required fields; set active |
| Expected Result | Home saved, appears in Homes list as active |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-02 — Edit Home

| | |
| --- | --- |
| Role | JM Administrator / Referral Manager / Care Coordinator |
| Preconditions | Active Home exists |
| Steps | Edit name/address/manager fields; save |
| Expected Result | Changes persist; no occupancy rows rewritten |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-03 — Inactivate empty Home

| | |
| --- | --- |
| Role | Manager with `jmrs_manage_homes` |
| Preconditions | Home has no active residents |
| Steps | Set Home inactive |
| Expected Result | Home inactive; excluded from capacity/vacancy |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-04 — Reactivate Home

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Inactive empty Home |
| Steps | Set Home active |
| Expected Result | Home active again |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-05 — Cannot inactivate Home with active residents

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Home has at least one active occupancy |
| Steps | Attempt to inactivate Home |
| Expected Result | BLOCKED with clear error |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-06 — Home manager display

| | |
| --- | --- |
| Role | Viewer with `jmrs_view_homes` |
| Preconditions | Home has manager assigned (if used) |
| Steps | Open Home view / list |
| Expected Result | Manager display matches stored assignment |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-07 — Search/filter Homes

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Multiple Homes |
| Steps | Use search/status filters |
| Expected Result | Results match filters; no unrelated Homes |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-08 — Responsive Home screens

| | |
| --- | --- |
| Role | Any permitted |
| Preconditions | Long home names exist |
| Steps | View Homes list/detail at desktop, tablet, mobile |
| Expected Result | No overlap; long names wrap; no horizontal page overflow |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## B. Bedrooms

### SL-UAT-10 — Add Bedroom

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active Home |
| Steps | Add Bedroom with unique label |
| Expected Result | Bedroom active; Home capacity increases by 1 |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-11 — Duplicate room label blocked (same Home)

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Bedroom “1” exists in Home A |
| Steps | Add another “1” in Home A |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-12 — Same room label allowed in another Home

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Home A has “1”; Home B exists |
| Steps | Add “1” in Home B |
| Expected Result | Allowed |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-13 — Inactivate vacant Bedroom

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Vacant active bedroom |
| Steps | Inactivate bedroom |
| Expected Result | Capacity decreases; bedroom not in vacancy board |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-14 — Cannot inactivate occupied Bedroom

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Occupied bedroom |
| Steps | Attempt inactivate |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-15 — Reactivate Bedroom

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Inactive vacant bedroom |
| Steps | Reactivate |
| Expected Result | Capacity increases; available for placement |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-16 — Capacity follows ACTIVE bedroom count

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Mix of active/inactive bedrooms |
| Steps | Compare capacity on Homes list, Dashboard, Reports snapshot |
| Expected Result | Capacity = count of active bedrooms only |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-17 — Bedroom with occupancy history cannot silently change Home

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Bedroom has past or current occupancy |
| Steps | Attempt to move bedroom to another Home (if UI exposes) |
| Expected Result | Blocked or not offered; history remains coherent |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## C. Placement

### SL-UAT-20 — Place eligible client into vacant bedroom

| | |
| --- | --- |
| Role | Manager with `jmrs_manage_occupancies` |
| Preconditions | Supported Living (or NULL) client; vacant bedroom |
| Steps | Place client |
| Expected Result | Active occupancy; room occupied; current placement shown; `placement_started` activity |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-21 — Second client into occupied bedroom

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Occupied bedroom |
| Steps | Place another client into same bedroom |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-22 — Same client placed twice

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Client already has active occupancy |
| Steps | Place again into another/same room |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-23 — Archived referral placement

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Archived referral |
| Steps | Attempt place |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-24 — Own-home client placement

| | |
| --- | --- |
| Role | Manager |
| Preconditions | `care_setting = own_home` |
| Steps | Attempt place into bedroom |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-25 — NULL care setting placed from vacancy workflow

| | |
| --- | --- |
| Role | Manager |
| Preconditions | `care_setting` NULL; vacant bedroom |
| Steps | Place via vacancy/placement workflow |
| Expected Result | Placement succeeds; `care_setting` becomes `supported_living` |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## D. Transfer

### SL-UAT-30 — Transfer Rosewood Bedroom 1 → Oak Bedroom 2

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active resident in Rosewood Bed 1; Oak Bed 2 vacant |
| Steps | Transfer |
| Expected Result | Old occupancy ended; new active; old room vacant; new occupied; history retained; `placement_transferred` once |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-31 — Same-day transfer allowed

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active placement; vacant destination |
| Steps | Transfer with same-day effective date |
| Expected Result | Allowed |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-32 — Transfer to occupied room blocked

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Destination occupied |
| Steps | Transfer |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-33 — Transfer to inactive bedroom blocked

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Destination bedroom inactive |
| Steps | Transfer |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-34 — Transfer to inactive Home blocked

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Destination Home inactive |
| Steps | Transfer |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-35 — Failed destination does not leave old placement ended

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active placement; force invalid destination if safely testable |
| Steps | Attempt transfer that fails after validation |
| Expected Result | Source occupancy remains active (transactional rollback) |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## E. Move out

### SL-UAT-40 — End active placement

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active occupancy |
| Steps | End placement with valid move-out date |
| Expected Result | Occupancy ended; move_out_date set; bedroom vacant; history remains; `placement_ended` |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-41 — move_out earlier than move_in blocked

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active occupancy with known move_in |
| Steps | End with move_out before move_in |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-42 — Ending occupancy does not auto-switch care_setting

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Supported Living resident |
| Steps | End placement |
| Expected Result | care_setting remains supported_living (awaiting placement) unless manually changed |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## F. Care Setting

### SL-UAT-50 — Legacy NULL care setting

| | |
| --- | --- |
| Role | Staff with referral view |
| Preconditions | Referral with NULL care_setting |
| Steps | Open referral / lists / reports |
| Expected Result | Shown as Not Specified; clinical modules still work |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-51 — Set Supported Living

| | |
| --- | --- |
| Role | Editor |
| Preconditions | Editable referral |
| Steps | Set care_setting to Supported Living |
| Expected Result | Saved; activity logged without fabricating placement |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-52 — Supported Living without placement

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | SL client, no active occupancy |
| Steps | View referral / Reports snapshot |
| Expected Result | Awaiting / no active placement; counted in Supported Living + Awaiting Placement |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-53 — Set Own Home

| | |
| --- | --- |
| Role | Editor |
| Preconditions | No active occupancy |
| Steps | Set Client's Own Home |
| Expected Result | Own-home panel; address as service location; no bedroom controls |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-54 — Active resident SL → Own Home blocked

| | |
| --- | --- |
| Role | Editor |
| Preconditions | Active SL occupancy |
| Steps | Change care_setting to Own Home |
| Expected Result | BLOCKED |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-55 — End placement then switch to Own Home

| | |
| --- | --- |
| Role | Editor |
| Preconditions | Ended SL placement |
| Steps | Set Own Home |
| Expected Result | Allowed; placement history retained |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## G. Own-home address

### SL-UAT-60 — Edit own-home client address

| | |
| --- | --- |
| Role | Authorised editor |
| Preconditions | Own-home client |
| Steps | Edit address fields; save |
| Expected Result | Address updates |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-61 — Address update activity contains no full address

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Address just changed |
| Steps | Open activity timeline |
| Expected Result | Activity notes change without dumping full street address |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-62 — Incomplete address warning only

| | |
| --- | --- |
| Role | Editor |
| Preconditions | Own-home client |
| Steps | Save with incomplete address |
| Expected Result | Soft warning; save allowed; workflows usable |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-63 — Address changes update FUTURE visit display

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Future open visit; own-home client |
| Steps | Change address; reopen future visit |
| Expected Result | Current service location reflects new address; snapshot fields still NULL |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-64 — Executed visit retains old address snapshot

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Visit executed at prior address |
| Steps | Change address after execution |
| Expected Result | Historical visit snapshot unchanged |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## H. Home Dashboard

### SL-UAT-70 — Capacity / occupied / vacant / occupancy %

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Home with mixed occupancy |
| Steps | Open Home Dashboard |
| Expected Result | Metrics match shared `OccupancyService::compute_metrics()` |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-71 — Current residents only

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Active + former residents |
| Steps | View residents list |
| Expected Result | Only active occupancies |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-72 — Former residents excluded

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Ended occupancy |
| Steps | View current residents |
| Expected Result | Former resident absent |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-73 — Own-home clients excluded

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Own-home client exists |
| Steps | View Home Dashboard residents |
| Expected Result | Own-home client not listed |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-74 — Upcoming visits current residents only

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Visits for current and non-residents |
| Steps | View upcoming visits panel |
| Expected Result | Current Home residents only (per product rules) |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-75 — Transfer moves resident between dashboards

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Transfer between two Homes |
| Steps | Transfer; open both dashboards |
| Expected Result | Immediate move; source loses resident; destination gains |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-76 — End placement removes resident immediately

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Active resident |
| Steps | End placement; refresh dashboard |
| Expected Result | Resident removed; vacant count updates |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-77 — Attention values match source modules

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Overdue care-plan review / visit review / MAR exception exist |
| Steps | Compare Home Dashboard attention vs source screens |
| Expected Result | Counts/rules agree (reuse, not reinvent) |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-78 — Restricted referral visibility

| | |
| --- | --- |
| Role | Support Worker / scoped user |
| Preconditions | Resident outside AccessPolicy scope |
| Steps | Open Home Dashboard resident links |
| Expected Result | Restricted placeholder; no PII leak |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## I. Service location

### SL-UAT-80 — Supported Living current service location

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Active SL placement |
| Steps | Open schedule/visit/referral location panel |
| Expected Result | Shows Home + bedroom labels (not fabricated) |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-81 — Own-home current service location

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Own-home client with address |
| Steps | Open location panel |
| Expected Result | Client address as service location |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-82 — NULL setting unresolved

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | care_setting NULL |
| Steps | Open location panel |
| Expected Result | Unresolved; soft warning |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-83 — SL without placement unresolved

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | SL, no active occupancy |
| Steps | Open location panel |
| Expected Result | Unresolved / awaiting placement |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-84 — Future visit follows current location

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Open future visit |
| Steps | View visit |
| Expected Result | Current resolved location; snapshot NULL |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-85 — Transfer changes future visit location dynamically

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Future visit; active SL placement |
| Steps | Transfer Home; reopen future visit |
| Expected Result | Location updates to new Home; snapshot still NULL |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## J. Historical visit location (critical)

### SL-UAT-86 — Execute at Oak → snapshot Oak

| | |
| --- | --- |
| Role | Executor / Manager |
| Preconditions | Client at Oak; Visit A scheduled |
| Steps | Execute Visit A |
| Expected Result | Snapshot type/home = Oak; outcome recorded |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-87 — Transfer to Rosewood; Visit A remains Oak

| | |
| --- | --- |
| Role | Manager / Viewer |
| Preconditions | SL-UAT-86 passed |
| Steps | Transfer client Oak → Rosewood; reopen Visit A |
| Expected Result | Visit A historical location still Oak |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-88 — Future Visit B at Rosewood (snapshot NULL)

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Client at Rosewood after transfer |
| Steps | Create/view future Visit B |
| Expected Result | Current location Rosewood; snapshot fields NULL |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-89 — Execute Visit B → snapshot Rosewood; later Willow transfer keeps history

| | |
| --- | --- |
| Role | Executor / Manager |
| Preconditions | Visit B open at Rosewood |
| Steps | Execute Visit B; transfer to Willow; compare Visit A/B/future |
| Expected Result | A=Oak; B=Rosewood; future=Willow until executed |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-89a — Legacy executed visit without snapshot

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | visit_outcome set; service_location_type NULL |
| Steps | View visit / Visit Reports Unresolved filter |
| Expected Result | Location Not Recorded; not inferred from current Home |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-89b — Manual completed without execution

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | completed status, empty outcome, no snapshot |
| Steps | View historical context / reports |
| Expected Result | No fabricated historical location |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## K. Schedules & visits

### SL-UAT-90 — Schedule shows current service location

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Schedule for client with known location |
| Steps | Open schedule UI |
| Expected Result | Current location shown |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-91 — Generate Visits shows current service location

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Schedule ready to generate |
| Steps | Open generate screen |
| Expected Result | Current location guidance shown |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-92 — Generated visit snapshot fields remain NULL

| | |
| --- | --- |
| Role | Manager |
| Preconditions | Generate visits |
| Steps | Inspect generated visit row |
| Expected Result | Snapshot columns NULL until execution |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-93 — Visit Execution shows “Care Will Be Recorded At”

| | |
| --- | --- |
| Role | Executor |
| Preconditions | Open visit |
| Steps | Open execute UI |
| Expected Result | Current location labelled as where care will be recorded |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-94 — Execution writes snapshot

| | |
| --- | --- |
| Role | Executor |
| Preconditions | Open visit |
| Steps | Execute successfully |
| Expected Result | Snapshot written in same update as outcome |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-95 — Manager Review uses historical snapshot

| | |
| --- | --- |
| Role | Reviewer |
| Preconditions | Executed visit awaiting review; client later moved |
| Steps | Open manager review |
| Expected Result | Shows historical snapshot, not current Home |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-96 — Unresolved location does not block execution

| | |
| --- | --- |
| Role | Executor |
| Preconditions | Unresolved service location |
| Steps | Execute visit |
| Expected Result | Soft warning; execution allowed; snapshot unresolved if recorded |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## L. Reporting (integrate reporting UAT)

Complete also: [UAT_SUPPORTED_LIVING_REPORTING.md](UAT_SUPPORTED_LIVING_REPORTING.md).

### SL-UAT-100 — Current Snapshot counts

| | |
| --- | --- |
| Role | Report viewer (`jmrs_view_reports`) |
| Preconditions | Known estate |
| Steps | Open Reports Snapshot |
| Expected Result | Active Homes, Capacity, Occupied, Vacant, Occupancy %, SL / Awaiting / Own Home / Not Specified agree with operational screens |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-101 — Vacancy report semantics

| | |
| --- | --- |
| Role | Report viewer + `jmrs_view_homes` |
| Preconditions | Never occupied + previously occupied vacant + occupied + inactive bedrooms |
| Steps | Open Vacancy Report; filter Home; export CSV |
| Expected Result | Vacant Since / Never occupied; occupied/inactive absent; CSV matches screen |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-102 — Movement reporting Place → Transfer → End

| | |
| --- | --- |
| Role | Report viewer |
| Preconditions | Period with one place, one transfer, one end |
| Steps | Open Placement Movements for period; export CSV |
| Expected Result | New=1, Transfer=1, Ended=1; transfer not double-counted; recorded-at semantics for backdated ops dates |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-103 — Visit reporting context / transfer durability

| | |
| --- | --- |
| Role | Report viewer |
| Preconditions | Sequence from SL-UAT-86–89 |
| Steps | Filter Visit Care Delivery / Visit Home |
| Expected Result | Future follows current Home; executed stays on snapshot Home; Own Home historical durable; legacy = Location Not Recorded |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-104 — Cross-screen count agreement

| | |
| --- | --- |
| Role | Viewer |
| Preconditions | Active Home |
| Steps | Compare Homes List, Vacancy Board, Home Dashboard, Reports Snapshot, Reports Vacancy |
| Expected Result | Capacity/Occupied/Vacant/Occupancy % agree |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## M. Permissions & privacy

### SL-UAT-110 — Role matrix (Supported Living)

| Role | Homes view | Homes/Occupancy manage | Reports | Expected |
| --- | --- | --- | --- | --- |
| JM Administrator | Yes | Yes | Yes | Full |
| Referral Manager | Yes | Yes | Yes | Per current mapping |
| Care Coordinator | Yes | Yes | Yes | Per current mapping |
| Assessor | Yes (`jmrs_view_homes`) | No | No | Read-only Homes; no placement mutation; no Reports |
| Support Worker | No | No | No | No estate-wide Homes/Occupancy/Reports |

| | |
| --- | --- |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-111 — Privacy surfaces

| | |
| --- | --- |
| Role | Manager / Report viewer |
| Steps | Review Home Dashboard, Vacancy CSV, Snapshot CSV, Movements CSV, Visit CSV, activity, URLs |
| Expected Result | No diagnoses/medications/care-plan narrative; vacancy without resident PII; aggregates without resident PII; movements only authorised identity/activity; no full service addresses in management CSVs/activity/URLs/logs |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## N. Responsive

### SL-UAT-120 — Responsive pass

| | |
| --- | --- |
| Role | Any permitted |
| Steps | Desktop / tablet / mobile: Referrals, Home List, Home Dashboard, Vacancy Board, Placement forms, Visit screens, Reports |
| Expected Result | Long emails/home/room/client/address wrap; no overlap; no page overflow |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## O. Clinical & public regression

### SL-UAT-130 — Clinical smoke

| | |
| --- | --- |
| Role | Clinical staff |
| Steps | Assessment, Care Plan, Review, Care Team, Medication, MAR, Schedules, Visits, Tasks, Execute, Manager Review, Documents, Notes, Activity |
| Expected Result | All functional |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-131 — Public referral wizard

| | |
| --- | --- |
| Role | Anonymous / public |
| Steps | Step navigation; hidden final submit; required validation; successful submit; receipt; notifications |
| Expected Result | Production-style behaviour intact; no UI redesign |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## P. Activation / upgrade

### SL-UAT-140 — Clean activation (where practical)

| | |
| --- | --- |
| Role | Administrator |
| Steps | Activate plugin on clean/staging site |
| Expected Result | Activates; DB `2.21.0`; tables/columns present; roles synced; no fatal |
| Actual Result | |
| Pass / Fail | |
| Notes | |

### SL-UAT-141 — Upgrade from v1.1.x / pre-Supported-Living

| | |
| --- | --- |
| Role | Administrator |
| Preconditions | Backup taken |
| Steps | Deploy 1.2.0 files; load admin |
| Expected Result | Migrator reaches `2.21.0`; existing referrals/clinical data preserved; portal routes work; no activation fatal |
| Actual Result | |
| Pass / Fail | |
| Notes | |

---

## Sign-off

| Role | Name | Overall Pass/Fail | Date |
| --- | --- | --- | --- |
| Tester | | | |
| Product / ops | | | |

**Production recommendation:** Promote only after mandatory SL-UAT cases and reporting UAT pass, with backup + rollback ZIP identified (`docs/RELEASE_CHECKLIST.md`).
