# UAT — Phase 4D.1 Management Dashboard Data Integration

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `f7a9e8aadeef5bf5267c66311396546a00edc74a` (Phase 4C.1)

**Scope:** Operations tab and Needs Attention enrichment on the existing Management Dashboard (`/management/`) using real JMRS aggregates only.

**Out of scope:** Package-conversion / authority-SLA / placement / revenue metrics; assessment scheduling KPI; new route/capability/schema; emails; dashboard writes; exports; configuration UI; Phase 4D.2+.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Definitions

| Item | Rule |
| --- | --- |
| Access | `VIEW_DASHBOARD` + `VIEW_REFERRALS` and not Support Worker scoped (`PipelineAttentionService::current_user_can_view_pipeline_dashboard`) |
| Active referrals | Visible, non-archived; status not `completed` or `cancelled` |
| Archived | Excluded from operational cards/lists |
| Upcoming meetings | Status `scheduled`; `scheduled_at` in next **14 days** (site timezone); non-archived referral |
| Past scheduled meetings | Status still `scheduled`; `scheduled_at` &lt; now — **not** labelled missed/failed |
| Recent referrals | Limit **8** |
| Recent activity | Limit **10**; data-minimised descriptions |
| Workloads | Owner / Champion / Transition lead; display names + Unassigned; **not** performance scores |
| Assessment metric | **Deferred** |
| Stale inactivity | **Omitted** (no approved threshold) |
| Side effects | Dashboard GET must not mutate referrals/meetings/responsibilities/assessments, create activity, or send email |

---

## Activation and core dashboard

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Management Dashboard route opened | **PASS** |
| Operations tab opened and switched correctly | **PASS** |
| Existing Pipeline section unchanged | **PASS** |
| Existing Homes section unchanged | **PASS** |
| Existing Ownership section unchanged | **PASS** |
| Existing Actions / Needs Attention unchanged except approved operational enrichment | **PASS** |

---

## Status and workflow data

| Check | Result |
| --- | --- |
| Status cards displayed correctly | **PASS** |
| Active-referral count displayed correctly | **PASS** |
| New count displayed correctly | **PASS** |
| In progress count displayed correctly | **PASS** |
| Completed count displayed correctly | **PASS** |
| Upcoming-meeting count displayed correctly | **PASS** |
| Past-scheduled-meeting count displayed correctly | **PASS** |
| Workflow-stage distribution displayed correctly | **PASS** |
| Workflow stages remained in canonical order | **PASS** |
| Counts were visible as text | **PASS** |
| Archived referrals excluded from operational totals | **PASS** |

---

## Meeting aggregates

Two synthetic scheduled meetings were created on **referral 7**:

- Phase 4D.1 Upcoming Meeting UAT  
- Phase 4D.1 Past Scheduled Meeting UAT  

| Check | Result |
| --- | --- |
| Upcoming meeting count increased by 1 | **PASS** |
| Upcoming meeting appeared in the correct section | **PASS** |
| Upcoming meeting linked to the correct meeting detail | **PASS** |
| Past scheduled meeting count increased by 1 | **PASS** |
| Past scheduled meeting appeared in the correct section | **PASS** |
| Past scheduled meeting appeared in Needs Attention | **PASS** |
| Past meeting labelled objectively (not missed/failed) | **PASS** |
| No participant names or contact data displayed | **PASS** |
| No meeting online URL displayed | **PASS** |

---

## Responsibility aggregates

Referral **7** was temporarily set to:

- Referral owner: Unassigned  
- Champion: Unassigned  
- Transition lead: Unassigned  

| Check | Result |
| --- | --- |
| Unassigned owner count increased by 1 | **PASS** |
| Unassigned Champion count increased by 1 | **PASS** |
| Unassigned Transition lead count increased by 1 | **PASS** |
| Owner workload Unassigned count updated | **PASS** |
| Champion workload Unassigned count updated | **PASS** |
| Transition-lead workload Unassigned count updated | **PASS** |
| Needs Attention unassigned summaries updated | **PASS** |
| Referral status count remained unchanged | **PASS** |
| Workflow-stage count remained unchanged | **PASS** |

Original responsibility values were restored through the Staff Portal.

| Check | Result |
| --- | --- |
| `assigned_to` restored | **PASS** |
| `champion_user_id` restored | **PASS** |
| `transition_lead_user_id` restored | **PASS** |

Truthful responsibility activity may remain. Original user IDs / names were **not captured** (do not invent).

---

## Recent data

| Check | Result |
| --- | --- |
| Recent Referrals displayed a limited safe list | **PASS** |
| Referral references and links were valid | **PASS** |
| Recent Activity displayed a limited safe list | **PASS** |
| Activity descriptions remained concise | **PASS** |
| No client contact data displayed | **PASS** |
| No referrer contact data displayed | **PASS** |
| No attendee contact data displayed | **PASS** |
| No internal notes displayed | **PASS** |
| No assessment narrative displayed | **PASS** |

---

## Read-only behaviour

| Check | Result |
| --- | --- |
| Dashboard refresh created no activity | **PASS** |
| Repeated dashboard refresh created no activity | **PASS** |
| Dashboard GET changed no referral data | **PASS** |
| Dashboard GET changed no meeting data | **PASS** |
| Dashboard GET changed no responsibility data | **PASS** |
| Dashboard GET changed no assessment data | **PASS** |
| No `updated_at` changed because of dashboard viewing | **PASS** |
| No emails or notifications sent | **PASS** |

---

## Access and scope

| Check | Result |
| --- | --- |
| Administrator dashboard access | **PASS** |
| Referral Manager scoped dashboard access | **PASS** |
| Manager saw only referrals within existing authorised scope | **PASS** |
| Every displayed manager link was accessible to that manager | **PASS** |
| Assessor dashboard access denied | **PASS** |
| Support Worker dashboard access denied | **PASS** |
| Responsibility membership granted no dashboard access | **PASS** |
| Meeting-attendee membership granted no dashboard access | **PASS** |

---

## Responsive design (~375px)

| Check | Result |
| --- | --- |
| Cards stacked correctly | **PASS** |
| Operations sections did not overlap | **PASS** |
| Workflow-stage bars fitted the viewport | **PASS** |
| Tables used contained scrolling or responsive layout | **PASS** |
| Staff names wrapped correctly | **PASS** |
| Tabs remained usable | **PASS** |
| No page-level horizontal overflow | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| No emails | **PASS** |
| Workflow stages unchanged | **PASS** |
| Referral statuses unchanged | **PASS** |
| Restored responsibility values remained intact | **PASS** |
| Meeting data changed only through intentional UAT meeting creation and cleanup | **PASS** |
| Management Dashboard remained read-only | **PASS** |

---

## Synthetic data cleanup

| Item | Result |
| --- | --- |
| Referral used | **7** (retained) |
| Synthetic purpose prefix | `Phase 4D.1%` |
| Synthetic meeting attendees | Removed |
| Synthetic meetings | Removed |
| Responsibility values | Restored via Staff Portal |
| Workflow stage | Unchanged |
| Referral status | Unchanged |
| Activity Timeline | Truthful UAT activity may remain; not deleted or rewritten |

Do **not** claim referral 7 or its audit history was deleted.

---

## Deferred metrics (preserved)

- Standalone assessment scheduling KPI  
- Package-costing metrics  
- Local-authority decision or SLA metrics  
- Placement-conversion / target-home / transition-completion metrics  
- Revenue and projected-income metrics  
- Unsupported performance targets  
- Stale-referral threshold without an approved business rule  

---

## Confirmations

- Product **1.4.0** · DB **2.29.0** · rewrite **1.2.7**  
- No schema change · no new route · no emails  
- Dashboard GET is read-only (no workflow / responsibility / meeting / assessment writes)  
- Aggregates are scope-aware; archived excluded from operational totals  
- Focused manual staging UAT **PASS** 2026-08-27  

| Tester | Context | Date | Result |
| --- | --- | --- | --- |
| Staging focused UAT | Phase 4D.1 Management Dashboard | 2026-08-27 | **PASS** |
