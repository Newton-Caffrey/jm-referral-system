# Supported Living — Homes, Bedrooms, Occupancy & Care Setting

**Target product release:** v1.2.0  
**Database schema tip:** `2.21.0`  
**Phases:** 2B Homes & Bedrooms · 2C Occupancy & Placements · 2D Care Setting & Own-Home · 2E Home Operational Dashboard · 2F.1 Service Location Foundation · 2F.2 Service Location UI & Own-Home Address

---

## Architecture decisions

| Decision | Detail |
| --- | --- |
| Canonical care record | `jmrs_referrals.id` remains lifelong care-record ID |
| No Clients table | Service users stay on the referral record for v1.2 |
| `service_type` | **WHAT** care is delivered (e.g. Personal Care) |
| `care_setting` | **WHERE / how** care is delivered (`supported_living` \| `own_home` \| NULL) |
| Dimensions | Service type and care setting are independent |
| Occupancy | Historical rows in `jmrs_occupancies` (Supported Living only) |
| Own-home location | Existing referral address fields (`address_line_1`…`postcode`) — no duplicate service-address columns yet |
| Service location (current) | Unexecuted visits resolve **dynamically** from current care setting / occupancy / referral address |
| Service location (historical) | Executed visits freeze a denormalized snapshot on `jmrs_care_visits` at `VisitExecutionService::execute()` |
| Future visits | Snapshot columns stay NULL; never rewritten on transfer/placement/care-setting/address change |
| Bedrooms | Never store `referral_id` / `client_id` |
| Clinical modules | Shared for both care settings (no forked assessment/care plan/MAR/etc.) |

---

## Care setting (`jmrs_referrals.care_setting`)

| Value | Meaning |
| --- | --- |
| `NULL` | Legacy / not yet classified — all clinical workflows still allowed |
| `supported_living` | JM property placement model (occupancy optional until placed) |
| `own_home` | Care at client's residential address |

Domain helper: `JMReferral\Referral\CareSetting`.

### Rules

- Changing to **Own Home** is **blocked** while an **active** Supported Living occupancy exists (end/transfer first). Historical occupancy is kept.
- Setting **Supported Living** does **not** require an immediate occupancy (“awaiting placement”).
- **Place resident** is blocked when `care_setting = own_home`.
- Successful placement with `care_setting = NULL` sets `supported_living` **inside the same transaction** as the occupancy insert. Activity: single `placement_started` (no duplicate `care_setting_changed`).
- **Transfer** preserves/repairs `supported_living`. **End placement** does **not** auto-switch to `own_home`.
- Explicit staff changes via referral edit log `care_setting_changed`.
- Incomplete own-home address → soft warning only. Location resolution (`ServiceLocationResolver`) treats own-home as resolved by type; `address_complete` is separate (Line 2 optional).

Public referral wizard is **not** used to classify care setting.

---

## Service location (Phase 2F.1)

Read-only domain: `JMReferral\Visits\ServiceLocation` + `ServiceLocationResolver`.

### Current vs historical

| Visit state | Source of truth |
| --- | --- |
| Unexecuted (no `visit_outcome`) | Resolve **current** referral location dynamically |
| Executed (`visit_outcome` set) + snapshot | Use frozen columns on `jmrs_care_visits` |
| Executed + no snapshot (legacy) | `Location not recorded at time of visit` — **never** invent current location |
| Status `completed` without `visit_outcome` | Same legacy/unrecorded semantics (not verified execution) |

### Resolution rules (current)

| Care setting | Result |
| --- | --- |
| `supported_living` + active occupancy | Home + bedroom + home address; `source=occupancy` |
| `supported_living` + no placement | Unresolved — “No active Supported Living placement” |
| `own_home` | “Client's Own Home” + referral address; `source=referral_address` |
| `NULL` | Unresolved — “Care setting not specified” (no historical occupancy guess) |

### Snapshot at execution

Written **only** in `VisitExecutionService::execute()`, in the **same** `CareVisitRepository::update()` as outcome/status (single SQL write). Unresolved location does **not** block execution — type `unresolved` is frozen with a reason label.

Columns (all nullable, no backfill): `service_location_type`, `service_location_label`, `service_address_*`, `service_home_id`, `service_bedroom_id`, `service_occupancy_id`, `service_location_recorded_at`.

### Explicit non-goals (2F.1)

- No Schedule / Visit / Manager Review / Home Dashboard location UI panels (2F.2)
- No referral address edit UI (2F.2)
- No hooks rewriting future visit snapshot columns on transfer/placement/care-setting/address change
- Cancelled/missed visits are not auto-snapshotted

---

## Service location UI (Phase 2F.2)

Staff Portal surfaces `ServiceLocationResolver` results (no duplicated resolution logic in templates).

| Surface | Behaviour |
| --- | --- |
| Referral Care Setting panel | Current service location + soft warnings |
| Schedule form / Generate Visits | Current location (informational; generation still writes NULL snapshots) |
| Visit form / list | Unexecuted → current short/full label; executed → snapshot / legacy unrecorded |
| Visit execute | “Care will be recorded at” = current resolution; execution still allowed when unresolved |
| Manager review | Historical snapshot only |
| Cancelled/missed without snapshot | “Recorded service location unavailable” (+ optional current labelled separately) |
| Home Dashboard upcoming visits | Bedroom column only (home address not repeated) |

### Own-home address editing

Authorised staff edit `address_line_1/2`, `city`, `postcode` via the shared referral edit pipeline (`ReferralEditController` → `ReferralService::update` → `ReferralRepository`). Incomplete own-home address remains a soft warning. Activity: `client_address_updated` (“Client address updated.”) — no address text in the timeline.

Public referral form is unchanged.

### Explicit non-goals (2F.2)

- No DB schema change (remains `2.21.0`)
- No future-visit snapshot writes; no transfer/address hooks rewriting historical rows
- No new public AJAX location endpoints
- Visit print view: none in this codebase (N/A)

---

## Capacity / vacancy

- **Capacity** = count of **active** bedrooms for a home  
- **Occupied** = count of **active** occupancy rows for that home  
- **Vacant** = capacity − occupied  

Inactive bedrooms do not count toward capacity.

---

## Occupancy constraints

At most one **active** occupancy per bedroom.  
At most one **active** Supported Living bedroom occupancy per referral.

### Concurrency strategy

MySQL does not support partial unique indexes via dbDelta. Integrity is enforced by:

1. Application validation before mutation  
2. `START TRANSACTION`  
3. `SELECT … FOR UPDATE` on the referral row and destination bedroom row (and current occupancy row for transfer/end)  
4. Re-check active occupancy constraints inside the transaction  
5. Mutate then `COMMIT` (any failure → `ROLLBACK`)

Transfers end the old row and create a new active row in the same transaction so a failed destination create never leaves the client without a placement.

---

## Placement workflows

| Action | Effect |
| --- | --- |
| Place | Create active occupancy; log `placement_started` |
| Transfer | End old (same-day allowed) + create new; log `placement_transferred` |
| End / move out | Set status ended, move_out_date, ended_by/ended_at; log `placement_ended` |

History is newest-first. Ended rows are not hard-deleted.

---

## Inactivation protections (2C)

- Occupied bedroom cannot be made inactive  
- Home with any active occupancy cannot be made inactive  
- Managers must transfer/move out first  

Bedroom `home_id` is not editable (transfer is via occupancy records).

---

## Capabilities

| Cap | JM Admin | Referral Mgr | Care Coord | Assessor | Support Worker |
| --- | --- | --- | --- | --- | --- |
| `jmrs_view_homes` | ✓ | ✓ | ✓ | ✓ | — |
| `jmrs_manage_homes` | ✓ | ✓ | ✓ | — | — |
| `jmrs_manage_occupancies` | ✓ | ✓ | ✓ | — | — |

Estate occupancy board requires `jmrs_view_homes`. Placement mutations require `jmrs_manage_occupancies` plus referral `AccessPolicy::can_mutate_referral`.

Support Workers do **not** get estate-wide occupancy access.

---

## Staff Portal

```
Supported Living
  Homes
  Vacancies / Occupancy
```

Routes (default base `staff-portal`):

- `/homes/` … (Phase 2B)  
- `/occupancy/` — operational board  
- `/occupancy/place/` — place resident  
- `/occupancy/{id}/transfer/` — transfer  
- `/occupancy/{id}/end/` — end placement  

Referral care-record view shows:

- **Care Setting** panel (Set / Change Care Setting via referral edit)
- **Own-Home Support** panel when `own_home` (service location = client address; no Place/Transfer)
- **Supported Living** panel when `supported_living` (or active/historical occupancy): Place / Transfer / End as permitted
- Placement history retained after care-setting changes

Care setting classification uses existing referral edit permission (`can_mutate_referral`). No new capability.

---

## Home operational dashboard (Phase 2E)

No DB migration. Read model: `HomeDashboardService`.

Opening a Home shows:

| Section | Source |
| --- | --- |
| Summary + KPI cards | Shared `OccupancyService::compute_metrics()` — capacity = active bedrooms; occupied = active occupancies; vacant = capacity − occupied; % safe when capacity 0 |
| Current Residents | Active `jmrs_occupancies` for the home only (own-home clients excluded by design) |
| Bedrooms | Active/Inactive + Occupied/Vacant text labels; Place / View Client when permitted |
| Upcoming Visits | Batched visits for current resident `referral_id`s, today → +7 days |
| Operational Attention | Reuses existing rules only: overdue care-plan reviews (`review_date < today`), visits awaiting manager review, today’s MAR exceptions — counts for visible residents; no new clinical rules |

### Privacy

- Estate access still requires `jmrs_view_homes`
- Resident names / care-record links require `AccessPolicy::can_view_referral`
- Restricted residents show as “Restricted” without a care-record link
- Attention items require `jmrs_view_operational_alerts` (+ relevant module caps)
- Support Workers still have no estate-wide Homes access

Homes list / Vacancies board / Home Dashboard share the same occupancy metric formula.

---

## Reporting (Phase 2G.1)

WP Admin **J&M Referrals → Reports** only (`jmrs_view_reports`). Extends the existing ReportController / ReportService / ReportRepository stack — no second reports system, no portal Reports routes, no DB migration.

### Current snapshot semantics

Section label: **Supported Living — Current Snapshot**.

Copy: current estate and care-delivery position as of today. Figures are **not** affected by the selected report date range (referral/visit/medication/task/staff/compliance sections remain date-range driven).

### Active care-record definition

Same as Active Clients:

- `archived_at IS NULL` (via `ReportRepository::append_referral_access`)
- `status NOT IN ('completed', 'cancelled')`

### Estate KPIs

| Metric | Definition |
| --- | --- |
| Active Homes | `jmrs_homes.status = active` |
| Capacity | Active bedrooms in active homes (`estate_summary`) |
| Occupied | Active occupancies in active bedrooms in active homes |
| Vacant | `MAX(capacity - occupied, 0)` |
| Occupancy % | `occupied / capacity × 100` (0% if capacity = 0) via `OccupancyService::compute_metrics()` |

Inactive homes/bedrooms excluded from current estate metrics. Historical occupancy rows are retained but not counted as current occupied capacity when inactive.

### Care-delivery KPIs

| Metric | Definition |
| --- | --- |
| Supported Living Clients | Active care records + `care_setting = supported_living` |
| Awaiting Placement | Active SL + **no** active occupancy (aggregate count only — no client name list) |
| Own-Home Clients | Active + `care_setting = own_home` |
| Care Setting Not Specified | Active + `care_setting IS NULL` (or empty) |

Supported Living + Own Home + Not Specified should equal classified active care records (subject to any other stored care_setting values, which are not expected in normal operation).

### Charts & table fallback

- Care Delivery Setting — doughnut (SL / Own Home / Not Specified) + label/value table
- Occupancy by Home — grouped bar (Occupied / Vacant) for active homes + table: Home, Capacity, Occupied, Vacant, Occupancy %

Wired through `ReportService::chart_definitions()` and `assets/js/reports.js` (no standalone Chart.js in the template).

### Permissions & privacy

- Gate: `jmrs_view_reports` only (no extra `jmrs_view_homes` for aggregate cards)
- Aggregate management data only — no client names, addresses, diagnoses, medication, or care-plan narratives
- Role mappings unchanged (Assessor / Support Worker do not gain report access)

### CSV

Full report CSV includes a clearly named **Supported Living — Current Snapshot** Metric/Value summary. Section CSV for `supported_living_snapshot` exports the section datasets. No vacancy/placement-movement/resident-level CSVs in 2G.1.

### Performance

Batched queries: `estate_summary` + `count_active_homes` + grouped care-setting counts + awaiting-placement count + `occupancy_metrics_by_active_homes` (no N+1 per home).

---

## Vacancy reporting (Phase 2G.2)

WP Admin **Reports** section **Vacancy Report — Current Snapshot**. Current-state only — not controlled by the report date range. Home filter `jmrs_report_home` affects vacancy table, scoped metrics, and vacancy CSV only.

### Current vacancy definition

A bedroom is vacant when:

- `bedroom.status = active`
- parent `home.status = active`
- no `occupancy.status = active` for that bedroom

Same semantics as Homes List / Vacancies board / Home Dashboard / Phase 2G.1.

Inactive homes and inactive bedrooms are excluded.

### Vacant Since

| Case | Display |
| --- | --- |
| Previous ended occupancy | `MAX(move_out_date)` for that bedroom |
| No occupancy history | **Never occupied** (do not use `bedroom.created_at`) |

**Limitation:** If a bedroom was deactivated then reactivated, the schema does not record continuous vacancy periods. Vacant Since remains the most recent recorded occupancy end date — no fabrication, no schema change in v1.2.

Previous residents are never shown.

### Home filter & metrics

- Options: All Active Homes + each active home (validated server-side; invalid id falls back to all)
- All Homes → estate totals from 2G.1 snapshot
- Specific Home → that home’s Capacity / Occupied / Vacant / Occupancy % from the same `occupancy_metrics_by_active_homes()` rows (agrees with Home Dashboard)

### Permissions

- Page: `jmrs_view_reports`
- Detailed vacancy table / home filter / vacancy CSV: `jmrs_view_reports` **and** `jmrs_view_homes`
- Current role map: every report-enabled JM role (JM Admin, Referral Manager, Care Coordinator) already has both. Assessor has `VIEW_HOMES` but not reports. Support Worker has neither.
- No new capabilities.

### CSV

- Section export: `jmrs-supported-living-vacancies-YYYY-MM-DD.csv` — columns Home, Bedroom, City, Postcode, Vacant Since, Status; respects home filter
- Full report CSV: vacancy **summary** metrics only (bedroom rows stay section-specific to avoid breaking Metric/Value full-export format)

### Performance

One grouped vacancy query (`ReportRepository::list_current_vacancies`) plus reused 2G.1 occupancy summary — no N+1.

---

## Placement movement reporting (Phase 2G.3)

WP Admin **Reports** section **Placement Movements — Selected Period**. **Is** controlled by the existing Reports date range (`activity.created_at`).

### Authoritative source

`jmrs_referral_activity.action`:

| Action | UI / CSV label |
| --- | --- |
| `placement_started` | New Placement |
| `placement_transferred` | Transfer |
| `placement_ended` | Placement Ended |

Written by `OccupancyService` place / transfer / end. **Do not** infer movements from occupancy row inserts/ends (transfers create and end rows).

### Date semantics

Filter: `DATE(activity.created_at)` within the selected report period.

Means: “placement activity **recorded** in JMRS during the period” — not necessarily the effective/backdated move-in or move-out date.

### Activity schema findings

Columns: `id`, `referral_id`, `user_id`, `action`, `description`, `created_at`.

**No structured From/To metadata** (no home_id / bedroom_id JSON). Details use the existing human-readable `description` (e.g. “Transferred from Rosewood House — Bedroom 1 to Oak House — Bedroom 2.”). Do not parse descriptions for reporting logic.

**Home filter deferred:** `jmrs_report_home` is **not** applied to movements (would require structured event linkage or unsafe description parsing / current-occupancy joins). Estate-wide only in v1.2. Future enhancement: store structured From/To on activity (schema change — out of 2G.3).

### Historical event inclusion

Period reporting **does not** use the Active Clients filter (`archived_at` / completed / cancelled). Events remain if the care record later completes, cancels, or archives.

AccessPolicy **assigned-to** scope still applies when present (`append_referral_assigned_scope`). Page gate: `jmrs_view_reports`.

Current occupancy is **never** joined to rewrite historical movement location.

### Transfer counting

One `placement_transferred` activity = one Transfer. Never counted as an extra New Placement or Placement Ended.

### UI / CSV

- KPIs: New Placements, Transfers, Placements Ended, Total Placement Events
- Chart: Placement Movements bar (Chart.js via existing `reports.js`)
- Table: Recorded Date, Event, Referral Number, Client, Details (UI capped at 100 newest; CSV exports full filtered set)
- Section CSV: `jmrs-placement-movements-YYYY-MM-DD.csv`
- Full CSV: aggregate movement metrics only

Privacy: referral number, client name, and activity description (home/bedroom labels) only — no address/diagnosis/medication/care-plan narrative.

---

## Visit analytics by care delivery context (Phase 2G.4)

Extends the **existing** Visit Analytics / Visit KPIs on WP Admin **Reports**. No second Visit report, no DB migration (remains `2.21.0`).

### Filters (Visit-only)

| Param | Scope |
| --- | --- |
| `jmrs_visit_care_context` | Visit KPIs, Visit Analytics, visit-linked Task metrics, visits-completed Staff metrics, outstanding manager reviews |
| `jmrs_visit_home` | Same Visit surfaces only |

`jmrs_report_home` remains **Vacancy-only** (2G.2). Date range still applies to visits.

Care Delivery options: All / Supported Living / Client's Own Home / Unresolved / Location Not Recorded.

Home options: active Homes, plus inactive Homes that have historical snapshot visits in the selected date range (labelled `(Inactive)`).

### Classification (reporting layer)

Centralised in `VisitDeliveryContext` + `ReportRepository` joins (not per-KPI copies):

| Visit state | Care delivery / Home |
| --- | --- |
| Snapshot present (`service_location_type` non-empty) | Historical `service_location_type` / `service_home_id` |
| Open (`scheduled` / `confirmed` / `in_progress`) without snapshot | Current `referral.care_setting` + active occupancy Home |
| Terminal (completed/missed/cancelled/etc.) without snapshot | **Location Not Recorded** — never current Home |

Executed visits with snapshot stay on the Home recorded at execution after later transfers. Future visits follow current occupancy after transfers. Snapshot fields are not rewritten by reporting.

### Unresolved / Not Recorded

Includes: snapshot `unresolved`; open visits with NULL care setting or Supported Living without active occupancy; legacy executed / missed / cancelled / manual-completed without snapshot.

With Care Delivery = All and Home = All, Visit numbers match pre-2G.4 (legacy visits remain included).

### CSV / Full export

Visit section CSV and Full Report CSV preserve Visit care-context / Visit home query params the same way Vacancy already preserves `jmrs_report_home`. Full export documents active Visit filters when set. No street addresses in Visit analytics exports.

### Permissions / privacy

`jmrs_view_reports` + existing AccessPolicy assigned-to scope. No new capabilities. No service-location addresses on management analytics.

### Query strategy

Set-based SQL: `jmrs_care_visits` INNER JOIN referrals; LEFT JOIN active occupancy (`referral_id` + `status`) only when Visit filters are active. Snapshot predicates use columns on `jmrs_care_visits`. Occupancy indexes `(referral_id, status)` / `(home_id, status)` apply to open-visit Home matching.

---

## Reporting polish & UAT (Phase 2G.5)

Consolidation pass for 2G.1–2G.4. No new report modules, no DB migration (remains `2.21.0`).

### Final page flow

1. Report Period + Vacancy Home + Visit Analytics Filters (Reset Filters clears all)
2. Supported Living — Current Snapshot (estate + care delivery + charts)
3. Vacancy Report — Current Snapshot
4. Placement Movements — Selected Period
5. Existing KPI strips (Referrals → Visits → …) and Trends & Analytics (Visit Analytics with delivery context)

### Current Snapshot vs Selected Period

Sections labelled **Current Snapshot** or **Selected Period**. Date range does not change Snapshot/Vacancy. Visit filters do not change Vacancy/Movements. Vacancy Home does not change Visits/Movements.

### Exports

| Export | Contents |
| --- | --- |
| Snapshot section CSV | Aggregates only |
| Vacancy CSV | Bedroom/property vacancy rows (city/postcode; no residents/clinical) |
| Movements CSV | Full period event rows (UI capped at 100 newest) |
| Visit section CSV | Aggregate datasets + active Visit filter metadata |
| Full Report CSV | Metric/Value aggregates including Snapshot, Vacancy summary, Movement KPIs, Visit section under active Visit filters — not full vacancy/movement detail dumps |

### Known limitations

1. Movement period uses activity recorded-at time  
2. Home-specific historical movement filtering unavailable (no structured Home IDs on activity)  
3. Vacant Since = latest recorded occupancy end (or Never occupied)  
4. Monthly historical occupancy trend deferred  
5. Legacy historical visits without snapshots = Location Not Recorded  

UAT checklist: `docs/uat/UAT_SUPPORTED_LIVING_REPORTING.md`

### Release preparation (Phase 2H)

Product version `1.2.0` · DB `2.21.0`. Master UAT: `docs/uat/UAT_SUPPORTED_LIVING_V1_2.md`. Release gate: `docs/RELEASE_CHECKLIST.md`. Release notes: `docs/RELEASE_NOTES_v1.2.0.md`.

---

## Audit

- Occupancy rows are the historical record (`created_by`, `ended_by`, timestamps)  
- Referral-scoped events use existing `ReferralActivityService`  
- Home/bedroom property edits still do **not** fabricate referral activity  

---

## WP Admin

Operations remain **Staff Portal primary**. No duplicate occupancy admin app in Phase 2C.

---

## Manual acceptance tests

See Phase 2C brief tests A–L …
Phase 2D: tests A–M …
Phase 2E: tests A–K (home dashboard metrics, residents, vacant room, transfer/end count updates, own-home exclusion, upcoming visits, operational attention reuse, permissions, cross-page count agreement, clinical regression).
Phase 2F.1: tests A–L (current SL/own-home/NULL/no-placement resolution; execute snapshot; transfer durability; unexecuted dynamic after transfer; own-home freeze; post-execution address change; legacy unrecorded; unresolved execution; regression).
Phase 2F.2: tests A–P (referral/schedule/visit/execute/review UI; own-home address edit + durability; transfer/end-placement dynamic display; legacy/null care setting; permissions; regression).
Phase 2G.1: tests A–L (estate KPI agreement with Homes/Vacancy/Dashboard; care-setting counts; completed/cancelled exclusion; awaiting placement; inactive bedroom/home; date filter independence; charts vs tables; zero capacity; permissions; report regression).
Phase 2G.2: tests A–N (estate/home vacancies; Vacant Since / Never occupied; home filter; move-out/place/transfer; inactive bedroom/home; date filter independence; CSV; zero vacancies; cross-screen consistency; regression).
Phase 2G.3: tests A–N equivalents (transfer counted once; end after transfer; backdated recorded-at; completed/archived history retained; date presets; snapshot sections unchanged; permissions; CSV; regression).
Phase 2G.4: tests A–P (no-filter regression; future visit SL/home; transfer before/after execution; own-home then care-setting change; legacy/missed/cancelled without snapshot; open NULL care setting; SL no placement; date range; charts; CSV; permissions; 2G.1–2G.3 + clinical regression).
Phase 2G.5: `docs/uat/UAT_SUPPORTED_LIVING_REPORTING.md` (filter clarity, empty states, CSV/privacy, responsive, historical/movement/vacancy/own-home UAT, regression).
