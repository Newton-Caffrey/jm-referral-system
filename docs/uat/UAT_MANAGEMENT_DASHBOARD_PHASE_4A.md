# UAT — Management Dashboard Phase 4A

Accuracy, privacy, and existing-data parity for the Management Dashboard.

**Product version:** remain **1.4.0** (no release in this phase)  
**Database version:** remain **2.28.0** (no migration)  
**Portal rewrite:** remain **1.2.2**

**Status:** Manual UAT **passed** on **2026-08-26** (development checkpoint; not a product release).

### Manual UAT result (2026-08-26)

Confirmed passed:

- Access and existing portal Dashboard regression
- Client initials / privacy (no full names on Management Dashboard)
- Here Now counts and Days in Stage
- All Who Reached with real historical First Reached dates
- Stage 1 (referral / interest)
- Stage 2 (“Appointment to Arrange”)
- Stage 3 (assessment)
- Stage 4 (Package Cost)
- Stage 5 (Authority Consideration)
- Stage 6 (Placement Transition)
- Homes & Capacity (occupied now vs future move-ins)
- Ownership totals
- Recommended Actions (existing JMRS attention rules)
- Responsive behaviour
- Print preview

**High-volume truncation (300+ rows):** not load-tested in UAT. Truncation note behaviour is **code-reviewed** only (`rows_note` when capped).

---

## Scope

Read-only Management Dashboard improvements using **existing** JMRS data only.

In scope:

- Historical stage-time accuracy (Here Now vs All Who Reached)
- Client privacy (referral number + initials; no full names on this board)
- Uncapped aggregate KPIs / stage counts / ownership / PPV / occupancy splits
- Stage 1–6 panel field enrichment from existing columns/tables
- Occupied now vs confirmed future move-ins vs projected occupancy
- Truncation notes when table rows are capped

Out of scope (deferred / blocked by schema or product phase):

- New DB tables/columns/migrations
- Workflow stage remaps / new mutation endpoints
- Commissioner / pre-assessment meeting model
- Multiple attendees
- Client champion
- Weekly hours / rates / annualised package value
- Panel date / 21-day LA threshold / prototype attention rules
- Proposed target home from costing
- Changes to Homes/Vacancies pages or occupancy mutations
- Commit / push / tag / ZIP / version bumps

---

## Privacy change

Applies **only** to the Management Dashboard payload and template.

| Before | After |
|--------|--------|
| Full `client_name` in tables / actions | `client_initials` derived in memory via `ManagementClientDisplay` |
| Full name in Recommended Actions | Scrubbed to initials without changing `PipelineAttentionService` |

Rules:

- Initials not persisted
- No full name in visible markup, titles, aria-labels, data attributes, or JS funnel payload
- Authorised referral records elsewhere unchanged

Example: `Raymond Reddington` → `R.R.`; empty → `—`.

---

## KPI aggregate semantics

Totals use dedicated aggregate SQL (not capped row sets). Row tables may still be limited (queue limit 300) with an explicit note: “Showing the first X of Y referrals”.

| KPI / metric | Source |
|--------------|--------|
| Live in pipeline | `count_active_by_pipeline_slugs` over `VisualStageMap::all_active_slugs()` |
| Acquisition referrals | Live + care_commenced + declined + not_proceeding (current stage) |
| Care commenced / awaiting LA / transition | Same slug counts |
| Proposed Package Value (live) | `sum_current_package_total_for_pipeline_slugs` — latest Package Cost by `MAX(id)` per live referral; excludes terminal care_commenced from live slug set |
| Here Now per visual stage | Sum of active counts for that stage’s canonical slugs |
| All Who Reached per visual stage | `count_distinct_reached_or_currently_in` — history ∪ current presence (no fabricated earlier milestones) |
| Ownership | `count_ownership_by_pipeline_slugs` on live acquisition slugs |
| Occupancy estate / per home | `estate_occupancy_split` / `capacity_occupancy_split_by_home_ids` |

Access/archived rules: same `AccessPolicy` assignee constraint and `archived_at IS NULL` as other commercial dashboards.

Terminology remains **Proposed Package Value** (not revenue; not annualised).

---

## Here Now semantics

- Rows: referrals whose **current** canonical stage maps to the visual stage
- **Days in stage:** from current `workflow_stage_entered_at` only
- Stage PPV: aggregate latest Package Cost for referrals currently in that visual stage’s slugs

---

## All Who Reached semantics

- Count: distinct referrals with history into any canonical slug of the visual stage, **or** currently present there (legacy without history)
- Table column: **First reached** = earliest `jmrs_referral_stage_history.created_at` where `to_stage_slug` ∈ visual stage slugs
- If no history row: First reached displays `—` (referral may still appear via current-stage legacy rule)
- Does **not** show current “Days in stage” as historical duration
- No history backfill

---

## Stage 1–6 displayed fields

### Stage 1 — Local authority referrals (`interest_required`)

Referral number, initials, funding/referrer (`referrer_organisation` / type), received date, days since received, interest state (truthful: Response required / Response recorded / Taken forward), response date/method/recipient, owner.

No presenting-need / clinical narrative.

### Stage 2 — Appointment to Arrange (`assessment_to_schedule`)

**Chosen label:** **Appointment to Arrange** (canonical stage means scheduling is still required; not “Appointment Set”).

Scheduling status honesty; optional scheduled date/time/location/contact/assessor if a record exists; does not claim booked when stage is still to schedule.

### Stage 3 — Assessment

Referral, initials, funding, assessment date, schedule, location, status, outcome (concise labels), assessor, owner. No clinical summary/recommendations. No “Champion”.

### Stage 4 — Package costing

Referral, initials, funding, Proposed Package Value, Package Cost status, prepared/sent dates and by, method, recipient, submission reference, owner. No hours/rates/profit/annualisation.

### Stage 5 — Authority consideration

Referral, initials, funding, PPV, sent date, days awaiting (from **sent_at**, not created_at), recipient/method/reference, submission/decision status from Package Cost + latest `jmrs_referral_la_decisions` when present, owner. No panel/21-day rules.

### Stage 6 — Placement transition

Referral, initials, funding, care setting, home, bedroom, confirmed move-in, days until move-in (non-negative; past → “Move date passed”), PPV, owner. Occupancy records only.

---

## Occupancy-now / confirmed-future / projected

| Term | Definition |
|------|------------|
| Occupied now | Active occupancy (`status=active`), bedroom+home active, `move_in_date <= today` |
| Confirmed future move-in | Active occupancy with `move_in_date > today` |
| Vacancies today | Active bedroom capacity − occupied now |
| Projected occupancy | Occupied now + confirmed future move-ins |

Future move-ins are **not** counted as occupied today. No “proposed at costing” (no target-home field yet). Dashboard read logic only — Homes pages unchanged.

---

## Known deferred features

- Separate commissioner / pre-assessment meeting entity
- Multiple appointment attendees
- Client champion role
- Package hours / rates / cost floor / profit
- LA panel date / officer role / 21-day threshold
- Prototype attention thresholds (48h / 3-day / etc.)
- Funding authority tab / full team performance metrics
- Target home proposed at costing

---

## Query / performance strategy

- Uncapped counts via grouped `COUNT` / `COUNT(DISTINCT)` / `SUM` with joins to stages/referrals
- Latest Package Cost / LA decision / occupancy via `MAX(id)` subquery joins batched by referral ID set
- Stage history first-reached via `MIN(created_at)` grouped by referral for the displayed ID set
- User display names resolved in batches from collected IDs (assignee, assessor, prepared/sent by)
- Table display remains capped; KPIs are not

---

## Security / access (unchanged)

- Read-only; no new POST/AJAX/REST mutations
- JM Administrator, Referral Manager, Care Coordinator (per AccessPolicy), authorised WP admin: allowed when both dashboard + referral view capabilities apply
- Assessor / Support Worker: denied
- Prepared statements; archived + assignee scope respected
- Escaped template output; no clinical long text on this board

---

## Manual UAT checklist

Do **not** tick as passed until performed.

### Access

- [x] Allowed roles can open `/management/`
- [x] Assessor / Support Worker denied
- [x] No write controls / forms on the page

### Privacy

- [x] Tables show referral number + initials only (no full client names)
- [x] Recommended Actions show initials; free text does not leak full names
- [x] View links still open the full referral (where authorised)

### Mode timing

- [x] Here Now shows **Days in stage** from current stage entry
- [x] All Who Reached shows **First reached** from history (or `—` if none)
- [x] Legacy current-stage referral without history still appears in its current visual stage when expected

### Aggregates / truncation

- [x] KPI / reached / here-now / ownership / PPV match full population (not row cap)
- [ ] ~~If >300 rows in a stage view, truncation note appears~~ — **not load-tested**; truncation note is **code-reviewed** only

### Stages 1–6

- [x] Stage 1 interest fields truthful
- [x] Stage 2 labelled **Appointment to Arrange**; not claimed booked solely by stage
- [x] Stage 3 outcome/assessor without clinical narrative
- [x] Stage 4 Package Cost fields; PPV not annualised
- [x] Stage 5 days awaiting from sent date; real LA decision when present
- [x] Stage 6 occupancy placement fields; no negative days until move-in

### Homes

- [x] Occupied now excludes future move-ins
- [x] Vacancies today = capacity − occupied now
- [x] Projected = occupied now + confirmed future
- [x] Estate totals consistent with per-home cards

### Regression

- [x] Funnel / tabs / print / responsive behaviour preserved
- [x] Pipeline Attention / Homes pages behaviour unchanged (existing portal Dashboard operational)
- [x] No console errors from management JS

---

## Developer notes (DTO)

Board payload row keys now use `client_initials` (not `client_name`). Actions likewise. Mode `reached` exposes `first_reached_label`; mode `now` exposes `waiting_days`. Stages include `rows_shown`, `rows_total`, `rows_truncated`, `rows_note`. Homes estate/cards expose `occupied_now`, `vacancies_today`, `future_move_ins`, `projected`, `projected_pct`.
