# JM Referral System — Performance & Scalability Audit

**Phase:** 5.4.1 (analysis) + 5.4.2A/B (mitigations marked in status column)  
**Date:** 2026-08-05 (audit); mitigations through 2026-08-05  
**Scope:** Complete plugin review — referrals, view, dashboard, alerts, reports, visits/schedules, documents, schema, PHP/DI, WordPress admin  
**Out of scope for 5.4.1:** Code changes, index application, premature optimization  
**Applied later:** Phase 5.4.2A (list/dashboard), Phase 5.4.2B (view pagination, generation batching, chunked CSV, indexes `2.16.0`)

This document records measured code behaviour and estimated impact at scale. It is not a load-test report; estimates assume typical MySQL on shared hosting (e.g. one.com), PHP request timeout ~30–60s, and no persistent object cache unless WordPress has one.

---

# Executive Summary

The plugin is functionally rich and correctly layered (Repository → Service → Controller), but several **request paths load unbounded result sets** and run **per-row or per-visit query fans**. Repository APIs often already support `LIMIT` / pagination; callers frequently pass `null` and load everything.

**Verdict:** Not production-safe at multi-thousand referral / visit scale without Phase 5.4.2 work. Current small pilots (tens–low hundreds of referrals) will feel slow on list/view/dashboard for managers; 10k+ referrals or dense visit schedules will time out or exhaust memory on the hottest paths.

**Hottest paths (after 5.4.2A/B):**

1. ~~Referral list with delete capability~~ → paginated; delete off list
2. ~~Referral view with many visits~~ → paginated visits + bulk load; remaining: docs/schedules/meds still full; nested service re-finds
3. ~~Dashboard double alert engine~~ → fixed
4. ~~Schedule generation N+1~~ → batched; still synchronous in one request (cap 2,000)
5. ~~CSV referral export full memory~~ → chunked stream

**Still open hotspots:** leading-wildcard search; Menu DI duplication; report `DATE()` predicates; unbounded documents/schedules on View.

**Good news:** Document downloads stream via `readfile()` (bounded by 10 MB upload cap). Report CSV exports are aggregate rows (low memory) and reuse `get_report_data()` shape for page/charts/CSV. Alert rules already use `LIMIT 200`. Care-plan review/version uniqueness and visit `generation_key` UNIQUE keys exist.

---

# Top Performance Risks

| ID | Severity | Area | Finding | Impact | Effort | Status |
| --- | --- | --- | --- | --- | --- | --- |
| PERF-C-001 | **Critical** | List | No pagination: `query($filters, null, …)` loads all matching referrals | Timeout / OOM at 1k–10k+ | Low | **Resolved** (5.4.2A) — default 20/page (20/50/100) |
| PERF-C-002 | **Critical** | List | `can_permanently_delete()` → 13 COUNTs **per list row** for delete-capable users | ~13N queries; list unusable at hundreds | Medium | **Resolved** (5.4.2A) — Delete removed from list/dashboard; View-only |
| PERF-C-003 | **Critical** | View | Unbounded visits + per-visit task generate / tasks×2 / meds×2 / MAR | ~10–15 SQL/unexecuted visit; 100 visits ≈ 1k+ queries | High | **Resolved** (5.4.2B) — visit page 20/50/100; bulk tasks/MAR/names; no GET task gen |
| PERF-C-004 | **Critical** | Dashboard | Alert engine runs **twice** (alerts widget + reports summary) | +11 heavy queries every manager dashboard | Low | **Resolved** (5.4.2A) — calculate once; pass counts to summary |
| PERF-C-005 | **Critical** | Schedules | Synchronous generation: SELECT+INSERT+task N+1 per occurrence | ~300–2,000 queries / 100 visits; timeouts | High | **Resolved** (5.4.2B) — batch key lookup + chunked INSERT IGNORE + batch tasks; max 2,000/request |
| PERF-H-001 | **High** | Export | Referral CSV loads entire filtered set (`find_by_filters` unlimited) | Memory/time at 10k–100k | Medium | **Resolved** (5.4.2B) — stream in chunks of 500 |
| PERF-H-002 | **High** | View | Care-plan versions load full `snapshot` LONGTEXT for every version | Memory on busy plans | Medium | **Reduced** (5.4.2B) — list omits snapshot; LIMIT 25 |
| PERF-H-003 | **High** | View | All child collections unbounded (notes, activity, docs, schedules, meds) | Memory + transfer | Medium | **Reduced** (5.4.2B) — activity/notes 50; reviews/versions 25; visits paginated (docs/schedules/meds still full) |
| PERF-H-004 | **High** | Alerts/Reports | Full 11-rule alert fan-out on every reports page and CSV | +11 queries; no cache | Medium | **Reduced** — dashboard once (5.4.2A); reports KPI+compliance reuse one `get_alerts()` per `get_report_data()` |
| PERF-H-005 | **High** | Dashboard | N+1 `ReferralRepository::find` when enriching visit/task widgets | +10–30 PK queries | Low | **Resolved** (5.4.2A) — JOIN `client_name` + batch display names |
| PERF-H-006 | **High** | Search | `LIKE '%…%'` on four referral columns | Full scans as table grows | Medium–High | Open |
| PERF-H-007 | **High** | Schema | Missing composites / `created_at` / care-plan `(plan_status, review_date)` | Alert/report/list sort scans | Low–Medium | **Resolved** (5.4.2B) — DB `2.16.0` composite indexes (see below) |
| PERF-H-008 | **High** | DI | `Menu` rebuilds repos/services Plugin already constructed | Extra CPU; duplicate graphs | Medium | Open |
| PERF-M-001 | **Medium** | Dashboard | Five separate status COUNTs vs one `GROUP BY status` | +4 avoidable queries | Low | Open |
| PERF-M-002 | **Medium** | Reports | Duplicate `get_visit_tasks_by_status` inside one report load | +1 query | Low | Open |
| PERF-M-003 | **Medium** | Reports | `DATE(column)` wrappers defeat indexes | Scan cost grows with volume | Medium | Open |
| PERF-M-004 | **Medium** | View | Duplicate referral/care-plan finds via nested services | +5–15 fixed queries | Low | Open |
| PERF-M-005 | **Medium** | Documents | `hash_file` on every private download | CPU/IO per download | Low | Open |
| PERF-M-006 | **Medium** | WP | Many `admin_init` handlers on every admin request | Small constant overhead | Low | Open |
| PERF-L-001 | **Low** | Assets | Report Chart.js gated to reports screen | Already good | — | OK |
| PERF-L-002 | **Low** | Caps | Repeated `current_user_can` | WP-cached; fine | — | OK |

**Finding count:** 21 numbered findings (5 Critical, 8 High, 6 Medium, 2 Low), plus table-level index recommendations below.

---

# Table-by-Table Analysis

Prefix: `{$wpdb->prefix}jmrs_*` (typically `wp_jmrs_*`).

| Table | Expected growth | Existing indexes (summary) | Missing / recommended (do not apply yet) | Likely bottleneck |
| --- | --- | --- | --- | --- |
| `referrals` | **High** | PK; status, priority, assigned_to, referral_source, service_type_id, workflow_stage_id, archived_at, archived_by | `created_at`; UNIQUE `referral_number`; `(archived_at, created_at)`; `(assigned_to, archived_at)`; `(archived_at, status, priority)` | Unpaginated list; leading-wildcard search; ORDER BY created_at |
| `referral_activity` | **Very high** | PK; referral_id, user_id, action | `(referral_id, created_at, id)` | Unbounded timeline on view |
| `referral_notes` | Medium–high | PK; referral_id, user_id | `(referral_id, created_at, id)` | Unbounded notes + LONGTEXT |
| `service_types` | Tiny | slug UNIQUE, status | — | Re-fetched; cache candidate |
| `workflow_stages` | Tiny | stage_order, status | — | Re-fetched; cache candidate |
| `referral_documents` | Medium | referral_id, attachment_id, uploaded_by, storage_type | `(storage_type, id)`; `(referral_id, created_at)` | Migration batches; unbounded list |
| `referral_assessments` | ~1:1 referrals | UNIQUE referral_id; assessor, outcome | `assessment_date` / `next_review_date` if queried | Wide LONGTEXT always selected |
| `referral_care_plans` | ~1:1 | UNIQUE referral_id; plan_status; assessment_id | `(plan_status, review_date)` | Alert overdue reviews |
| `care_plan_versions` | Medium | UNIQUE (care_plan_id, version_number); created_at | — | **snapshot LONGTEXT** on view list |
| `care_plan_reviews` | Medium | care_plan_id, review_date, outcome | `(care_plan_id, review_date)` | Low |
| `care_visits` | **Very high** | UNIQUE generation_key; referral_id, visit_date, visit_status, assigned_user_id, schedule_id, … | `(referral_id, visit_date, start_time)`; `(assigned_user_id, visit_date, visit_status)`; `(visit_status, visit_date)`; awaiting-review covering | Generation; unbounded get_by_referral; alerts |
| `care_team` | Medium | referral_id, user_id, assignment_status | `(referral_id, assignment_status)`; `(user_id, assignment_status)` | Alert NOT EXISTS / counts |
| `visit_schedules` | Medium | referral_id, status, team_assignment_id | `(referral_id, status)`; `(status, start_date)` | Alert active-without-team |
| `visit_tasks` | **Very high** (≤8×visits) | visit_id, task_status, display_order | `(visit_id, task_name)`; `(task_status, task_name)` | Generation exists checks; outstanding aggregates |
| `medications` | Medium | referral_id, medication_status, dates | `(referral_id, medication_status)` | Per-visit active med reload |
| `medication_administrations` | High | UNIQUE (medication_id, visit_id, scheduled_time); status, administered_time | `(referral_id, administration_status, administered_time)`; prefer range predicates over `DATE()` | Alerts/reports exception counts |

**No foreign keys** (by design): orphans are an integrity concern (Phase 5.3 diagnostic), not a DB cascade performance win.

---

# Query Analysis

## 1. Referral List

**Evidence:** `ReferralListController::render()` → `repository->query($filters, null, 1, …)`.

| Aspect | Current behaviour |
| --- | --- |
| Pagination | Repository supports `LIMIT`/`OFFSET`; **controller passes `$per_page = null`** |
| Sorting | `ORDER BY created_at DESC, id DESC` — **no `created_at` index** |
| Search | Four `LIKE %term%` columns — not sargable |
| Filters | status, priority, assignee, archive_scope (centralized) |
| Total count | Separate `COUNT(*)` via `count_by_filters` |
| Enrichment | Service/stage names **batched**; assignee names per unique user; **delete eligibility = 13 COUNTs/row** |

### Scale estimate (active list, manager with delete)

| Referrals | Rows in PHP | Core SQL | Dependency COUNTs | Rough total | Expected UX |
| --- | --- | --- | --- | --- | --- |
| 100 | 100 | ~2–4 | ~1,300 | ~1.3k+ | Slow but often completes |
| 1,000 | 1,000 | ~2–4 | ~13,000 | ~13k+ | Very slow / timeout risk |
| 10,000 | 10,000 | full set | ~130,000 | catastrophic | Fail |
| 100,000 | 100,000 | full set | ~1.3M | unusable | Fail |

Without delete capability, list still loads all rows (still Critical at 10k+), but avoids the 13N COUNTs.

## 2. Referral View

**Evidence:** `ReferralViewController::render()` eagerly loads the full clinical graph.

**Fixed / semi-fixed:** referral find, activity, notes, documents, assessment, care plan, reviews, versions (with snapshots), team, schedules (+ COUNT per schedule), meds, workflow options, retention COUNTs if delete UI shown.

**Dominant variable cost:** for each visit — task generation (possible exists×8 + inserts), tasks fetched twice, active medications (often twice), MAR rows, display names, occasional schedule find.

| Scenario | Est. plugin SQL |
| --- | --- |
| Sparse referral (few children, 0 visits) | ~30–50 |
| Full referral, ~20 visits (half unexecuted) | ~200–250 |
| Full referral, ~100 visits | **~1,000+** |

**N+1 / waste highlights:** write-on-read task generation during GET; version snapshots for list UI; duplicate `find` of referral/care plan through services; unbounded collections.

## 3. Dashboard

**Evidence:** `DashboardPage::render()`.

| Widget group | Queries (order of magnitude) |
| --- | --- |
| Referral COUNTs + pipeline + recent | ~8–10 |
| Optional (schedules, med exceptions, tasks) | ~3–5 |
| Visit widgets + N+1 referral enrich | ~1 + ≤10 each widget |
| Operational alerts (`calculate_alerts`) | **11** |
| Reports shortcut (`get_dashboard_summary` → alerts again + KPIs) | **~13** |
| **Manager total** | **~45–60** |

**Critical:** alerts calculated twice when both `VIEW_OPERATIONAL_ALERTS` and `VIEW_REPORTS` are present.

## 4. Operational Alerts

**Evidence:** `OperationalAlertService::calculate_alerts()` — always runs **11** finders, each `LIMIT 200`.

Rules scan/join referrals, assessments, care plans, care team, schedules, visits, visit tasks, medication administrations. Archived referrals excluded. Access may be constrained in SQL then re-checked in PHP.

**Duplication:** referrals and visits touched by multiple rules in one run; same engine re-run by dashboard (×2) and reports/export.

**LIMIT 200** caps PHP alert volume, not necessarily scan work if indexes miss multi-predicate filters.

## 5. Reports

**Evidence:** `ReportService::get_report_data()`.

| Consumer | Calculation reuse |
| --- | --- |
| Reports page + charts | **One** `get_report_data()` per page load; charts built in memory |
| Full/section CSV | Calls **`get_report_data()` again** (no cross-request cache) |

Approx **~40 SQL** per reports load (11 alerts + KPI COUNTs + section aggregates), plus WP user lookups for staff labels.

**CSV memory:** Aggregate label/value rows streamed to `php://output` — **memory risk is low**; **CPU/DB risk is high** (recompute + alert fan-out).

**Internal waste:** `get_visit_tasks_by_status` invoked twice; many `DATE(col)` filters.

## 6. Visits / schedule generation

**Evidence:** `ScheduleGenerationService::generate()` → per date: `find_by_generation_key` → `create` → `VisitTaskService::generate_for_visit` (re-find visit + care plan + ≤8 exists + inserts).

| Visits generated | Est. DB round-trips (full care-plan tasks) |
| --- | --- |
| 100 | ~2,000 |
| 10,000 | ~200,000 (linear model; single UI run capped ~12 months ≈ hundreds of daily visits) |

UNIQUE `generation_key` protects races but pre-SELECT still runs. No batch INSERT, no chunked progress.

## 7. Documents

**Evidence:** `ReferralDocumentController::handle_download` uses `readfile($file_path)` after headers — **does not load entire file into a PHP string**.

Upload cap **10 MB**. Private downloads may re-hash (`hash_file`) for integrity — CPU/IO cost, not memory OOM for typical sizes.

---

# Memory Analysis

| Path | Memory character | Risk |
| --- | --- | --- |
| Referral list (all rows) | Wide referral rows × N in PHP arrays | **High** at 10k+ |
| Referral CSV | Same as list (full set) then stream | **High** at 10k–100k |
| Referral view | Notes/activity/docs/visits + **version snapshots** + task/MAR graphs | **High** with many visits/versions |
| Report CSV | Small aggregate datasets | **Low** |
| Document download | Streamed `readfile` | **Low** (≤10 MB) |
| Schedule generation | One occurrence at a time + inserts | **Medium** (timeout before RAM) |
| Alert engine | ≤11 × 200 rows | **Low–Medium** |

PHP `memory_limit` on shared hosts (often 256M) will fail first on **unpaginated list/export** and **view with huge version snapshots**, not on report charts.

---

# Scalability Forecast

| Scale | List | View (busy client) | Dashboard (manager) | Generate 1 year daily visits | Reports |
| --- | --- | --- | --- | --- | --- |
| **100 referrals** | Usable; slow if delete COUNTs | OK if few visits | OK but double alerts wasteful | OK (~365 visits) | OK |
| **1,000 referrals** | Painful / timeout with delete COUNTs | Depends on visits/client | Alerts dominate | OK–slow | Slow scans |
| **10,000 referrals** | Fail without pagination | Fail if many visits loaded | Alert scans expensive | Per-client OK; fleet-wide heavy | Full-table-ish DATE scans |
| **100,000 referrals** | Unusable as built | Must paginate children | Needs cache / slim alerts | Must batch/async | Needs indexed ranges / summaries |

**Visit table** will outgrow referrals quickly (schedules × frequency). Treat `care_visits` + `visit_tasks` + MAR as the primary growth frontier.

---

# Recommended Optimizations

*(Do not implement in 5.4.1 — backlog for 5.4.2+.)*

1. **Paginate referral list** (use existing repository API); default 20–50/page.
2. **Stop per-row retention COUNTs on list** — show Delete always when cap+access, enforce safety on POST only; or one batched “has_any_child” flag / deferred check on View.
3. **Paginate / limit Referral View children** (visits first); never load all version snapshots for a version list (metadata-only + detail page already exists).
4. **Remove write-on-read** task generation from View GET; generate on visit create/execute or explicit action.
5. **Run alert engine once per request**; share result between dashboard alerts + reports summary; short TTL transient optional.
6. **Join client fields into dashboard visit queries** — eliminate N+1 `find(referral_id)`.
7. **Chunked / batched schedule generation** (multi-row insert, prefetch care plan, bulk task insert, progress UI).
8. **Chunked referral CSV export** (cursor/LIMIT loops).
9. **Add recommended indexes** via Migrator (after measuring EXPLAIN on staging).
10. **Rewrite `DATE(col)`** to range predicates (`>= day start AND < next day`).
11. **DI cleanup:** Menu should not reconstruct the Plugin graph; inject shared services.
12. **Optional:** object cache for service types / workflow stages; defer `hash_file` on download to audit jobs.

---

# Suggested Phase 5.4.2 Implementation Order

Ordered by **impact ÷ effort** and risk reduction:

| Step | Work | Addresses | Effort | Status |
| --- | --- | --- | --- | --- |
| **1** | Enable list pagination + archive-aware page size | PERF-C-001 | Low | **Done (5.4.2A)** |
| **2** | Remove list-row dependency COUNTs (View-only delete) | PERF-C-002 | Low–Medium | **Done (5.4.2A)** |
| **3** | Deduplicate alert calculation on dashboard | PERF-C-004, PERF-H-004 | Low | **Done (5.4.2A)** for dashboard |
| **4** | Dashboard visit enrich: JOIN / batch display names | PERF-H-005 | Low | **Done (5.4.2A)** |
| **5** | Combine dashboard status COUNTs | PERF-M-001 | Low | Open |
| **6** | Referral View visit pagination; no GET task gen; history limits; metadata versions | PERF-C-003, PERF-H-002, PERF-H-003 | High | **Done (5.4.2B)** |
| **7** | Chunked referral CSV export | PERF-H-001 | Medium | **Done (5.4.2B)** |
| **8** | Migrator composite indexes (`2.16.0`) | PERF-H-007 | Medium | **Done (5.4.2B)** |
| **9** | Schedule generation batching + care-plan prefetch + bulk tasks | PERF-C-005 | High | **Done (5.4.2B)** |
| **10** | Report/query sargable date ranges; remove duplicate task-status query | PERF-M-002, PERF-M-003 | Medium | Open |
| **11** | Plugin/Menu DI reuse | PERF-H-008 | Medium | Open |
| **12** | Search strategy (prefix / FULLTEXT / external) — only if needed | PERF-H-006 | High | Open |
| **13** | Paginate View documents/schedules/meds if lists grow | PERF-H-003 remainder | Medium | Open |

### Indexes intentionally not added (5.4.2B)

- `referrals.referral_number` UNIQUE — numbers already uniqueness-checked in app; adding UNIQUE needs data audit first.
- `referral_care_plans (referral_id, plan_status)` — `UNIQUE KEY referral_id` already covers referral lookups.
- `care_plan_reviews (care_plan_id, review_date)` — single-column keys already exist; low gain vs storage.
- `visit_tasks (visit_id, task_name)` — helpful for generation dedupe but not required after batch exists-map; defer until duplicate-task incidents.
- Redundant single-column keys that are prefixes of new composites were left in place (dbDelta-safe; MySQL may use either).

**Explicit non-goals for early 5.4.2:** rewriting business rules, cascading deletes, auto-repair orphans, Chart.js changes, timed retention purge.

---

# PHP & WordPress Notes

## PHP / DI

- `Plugin::registerReferralControllers()` builds a large graph on `plugins_loaded`.
- `Menu::__construct` often rebuilds repositories/services even when controllers are injected — duplicate allocations on every `admin_menu`.
- Multiple `new ScheduleRepository()` / `new CareTeamRepository()` instances within one bootstrap.
- Loops that copy arrays for template enrichment are fine at small N; dangerous when N is unbounded.

## WordPress

- Many controllers register `admin_init` handlers; most bail early — acceptable noise, not the primary bottleneck.
- Report assets correctly gated to the reports screen.
- Capability checks are cheap relative to SQL.
- Transients used for form flash only — opportunity for short-TTL alert/report snapshots if product accepts staleness.
- Object cache (Redis/Memcached) would help config tables and alert snapshots if the host provides it; do not assume it on shared hosting.

---

# Testing Guidance for Phase 5.4.2

After optimizations (not now), measure on staging:

1. Referral list at 1k / 10k rows — page time, query count (Query Monitor / Savvii-style profiling).
2. Referral view with 50 / 100 visits — query count and peak memory.
3. Manager dashboard with alerts + reports caps — confirm alert engine runs once.
4. Generate visits for 90-day daily schedule with full care-plan tasks — wall clock and queries.
5. Referral CSV export at 10k rows — memory peak.
6. EXPLAIN on alert finders before/after index migration.

---

# Related Documents

- `docs/PRODUCTION_AUDIT.md` — security / integrity (Phase 5.1)
- `docs/DATA_RETENTION_POLICY.md` — archive-first deletion (Phase 5.3)
- This file — performance only (Phase 5.4.1)

**Status:** Phase **5.4.2A** implemented (list pagination, retention COUNT removal on list/dashboard, dashboard alert reuse, dashboard visit enrichment). Remaining Critical items: View N+1, schedule generation. No index migration yet.
