# Services — JM Referral System

Major `*Service` classes under `src/`. Repositories are omitted here except as dependencies.

---

## Referral & retention

### `ReferralService`
- **Purpose:** Create/update referrals, workflow stage changes, dashboard aggregates.
- **Deps:** `ReferralRepository`, `ReferralNumberGenerator`, `ReferralActivityService`, `UserProvider`, `NotificationService`, `ServiceTypeService`, `WorkflowStageService`, `AccessPolicy`
- **Used by:** Admin create/edit/view/dashboard, `PublicReferralService`, `PortalController`
- **Related templates:** `templates/referrals/*`, `templates/dashboard/*`, portal dashboard/list

### `ReferralActivityService`
- **Purpose:** Append activity timeline rows (`log_*` helpers).
- **Deps:** `ReferralActivityRepository`
- **Used by:** Nearly all mutating clinical services

### `ReferralNoteService`
- **Purpose:** Add/list notes with access checks.
- **Deps:** Note + referral repos, activity, AccessPolicy
- **Used by:** `ReferralNoteController`

### `ReferralRetentionService`
- **Purpose:** Archive, restore, permanent delete, dependency blocking.
- **Deps:** `ReferralRepository`, `ReferralDependencyRepository`, activity, AccessPolicy; optional Phase 4B.1 meeting/attendee repos for permanent-delete cascade
- **Used by:** List/view retention actions, portal archived display helpers

---

## Documents

### `ReferralDocumentService`
- **Purpose:** Staff upload, public intake upload, download preparation, legacy migration batches, storage counts.
- **Deps:** Doc repo, referral repo, activity, AccessPolicy, `PrivateDocumentStorage`
- **Used by:** Document controllers, public intake, Settings migration, portal view downloads

---

## Meetings & responsibilities (Phase 4B.1 / 4B.2)

### `ReferralMeetingService`
- **Purpose:** Create draft/scheduled meetings; update details; schedule/reschedule; complete; cancel. Server-side lifecycle via `MeetingLifecyclePolicy`. Does not advance pipeline. Does not send emails. No reopen/delete.
- **Public API (4B.2.2 / 4B.2.5):** `create_draft`, `create_scheduled`, `update_details`, `schedule`, `reschedule`, `complete`, `cancel`. `schedule()` logs `meeting_scheduled`; `reschedule()` / `update_details()` skip persist/activity on no-op (`changed: false`). Generic status/mass-assignment of lifecycle columns is not accepted.
- **Deps:** Referral + meeting repos, activity, AccessPolicy (`can_manage_referral_meetings` + visibility), `MeetingLifecyclePolicy`
- **Used by:** Portal `MeetingsHandler` write routes (Phase 4B.2.2+)

### `MeetingLifecyclePolicy`
- **Purpose:** Allowed meeting actions by status; also gates internal attendee add/edit/remove and completed attendance correction.
- **Used by:** `ReferralMeetingService`, `MeetingAttendeeService`, `MeetingsHandler`

### `MeetingAttendeeService`
- **Purpose (4B.2.3–4B.2.4 public API):** Internal: `add_internal_attendee`, `update_internal_attendee`, `update_internal_attendance`, `remove_internal_attendee`, `eligible_internal_staff_for_meeting`. External: `add_external_attendee`, `update_external_attendee`, `update_external_attendance`, `remove_external_attendee`. Shared: `count_non_final_attendance` (all kinds). Forces kind; external forces `user_id=null`; ignores mass-assignment of lifecycle/ownership fields. External same-name participants allowed (no uniqueness rule). Contacts never logged. Draft/scheduled: full edit/remove; completed: final attendance correction only; cancelled/archived: mutations denied.
- **Deps:** Referral + meeting + attendee repos, activity, AccessPolicy, `UserProvider`, `MeetingLifecyclePolicy`
- **Used by:** Portal `MeetingsHandler` (staging UAT 4B.2.3 PASS; 4B.2.4 PARTIAL MANUAL UAT ACCEPTED with Batch 4 residual risk; **4B.2.5 focused UAT PASS** 2026-08-27). Composition: one service in `registerReferralControllers()` → `registerStaffPortal()` → sole `MeetingsHandler`.

### `ReferralMeetingReadService`
- **Purpose:** Read-only summary, paginated list, detail presentation; strips contact PII / online URL unless `can_view_referral_meeting_contacts`. Deleted staff display as “Unavailable user”.
- **Deps:** Meeting + attendee repos, AccessPolicy (`can_view_referral_meetings` / `can_view_referral_meeting_contacts` / `can_manage_referral_meetings`), `UserProvider`
- **Query strategy:** Grouped status counts; paginated list with total; attendee counts grouped by meeting IDs; detail attendees once; batched internal display names; prepared SQL. List/summary do not present external contact PII or online URLs.
- **Used by:** Portal `MeetingsHandler`, referral workspace summary (Phase 4B.2.1+).

### `ReferralResponsibilityService`
- **Purpose (4B.1 / 4C.1):** Assign/clear/update referral owner (`assigned_to`), champion (`champion_user_id`), and transition lead (`transition_lead_user_id`). Public API: `get_for_referral`, `update_responsibilities`, `assign_champion`, `clear_champion`, `assign_transition_lead`, `clear_transition_lead`. Eligibility: `UserProvider::is_assignable` (VIEW_REFERRALS). Gate: `can_assign_referral_responsibilities` + non-archived. Same user may hold multiple roles. No-op when unchanged. Activity only for changed fields (`assigned`/`reassigned`, champion_*, transition_lead_*). Does **not** send email, change workflow_stage, or grant access via champion/lead.
- **Deps:** ReferralRepository, activity, AccessPolicy, UserProvider
- **Used by:** Portal `ResponsibilitiesHandler` (focused UAT **PASS** 2026-08-27; one service → `registerStaffPortal()` → sole handler). Product **1.4.0** · DB **2.29.0** · rewrite **1.2.7**.

---

## Assessment & care plans

### `ReferralAssessmentService`
- **Purpose:** Load/save assessment; form mapping helpers.
- **Deps:** Assessment + referral repos, activity, AccessPolicy

### `ReferralCarePlanService`
- **Purpose:** Generate from assessment, blank plan, save/activate content.
- **Deps:** Care plan + referral + assessment repos, activity, AccessPolicy, review service

### `ReferralCarePlanReviewService`
- **Purpose:** Reviews, version snapshots, version view preparation.
- **Deps:** Review + version + care plan + referral repos, activity, AccessPolicy

---

## Care team, schedules, visits

### `CareTeamService`
- **Purpose:** Assign/update team members; active client counts for dashboard.
- **Deps:** Care team + referral + care plan repos, activity, AccessPolicy, `UserProvider`

### `ScheduleService`
- **Purpose:** Save schedules; list/count; name lookup by IDs.
- **Deps:** Schedule + referral + care plan + care team repos, activity, AccessPolicy

### `ScheduleGenerationService`
- **Purpose:** Expand schedules into visits (batched), skip existing `generation_key`.
- **Deps:** Schedule/visit/referral/care plan/care team repos, activity, AccessPolicy, `VisitTaskService`

### `CareVisitService`
- **Purpose:** Visit CRUD; lists for referral/dashboard; assignable staff helpers.
- **Deps:** Visit + referral + care plan repos, activity, AccessPolicy, `UserProvider`, `CareTeamService`, `VisitTaskService`

### `VisitExecutionService`
- **Purpose:** Execute/complete visits; manager review; awaiting-review / completed dashboards. On execute, freezes service-location snapshot with outcome in one visit UPDATE.
- **Deps:** Visit + referral repos, activity, AccessPolicy, `VisitTaskService`, `MedicationAdministrationService`, `ServiceLocationResolver`

### `ServiceLocationResolver`
- **Purpose:** Read-only current/historical service location (Supported Living occupancy or own-home address). Never mutates data. Unexecuted → dynamic; executed → snapshot or legacy-unrecorded.
- **Deps:** `ReferralRepository`, `OccupancyRepository`, `HomeRepository`, `BedroomRepository`
- **UI (2F.2):** Staff Portal panels via `ServiceLocationPresenter` + `templates/portal/partials/service-location.php` (no DB in templates)

### `VisitTaskService`
- **Purpose:** Generate/update/summarize visit tasks; bulk load by visit IDs.
- **Deps:** `VisitTaskRepository`, visit repo, care plan repo

---

## Medication

### `MedicationService`
- **Purpose:** Medication list CRUD; view/manage permission helpers.
- **Deps:** Med + referral repos, activity, AccessPolicy

### `MedicationAdministrationService`
- **Purpose:** MAR save for visit; exception counts; validity-on-date helpers; alert support.
- **Deps:** Admin + med + visit + referral repos, activity, AccessPolicy

---

## Catalogue

### `ServiceTypeService`
- **Purpose:** CRUD/active list; selectable checks for public/admin.
- **Deps:** `ServiceTypeRepository`, `ReferralRepository`

### `HomeService` / `BedroomService` / `OccupancyService` / `HomeDashboardService`
- **Purpose:** Supported living homes, bedrooms, historical placements (2B/2C), care-setting integration (2D), and home operational dashboard read model (2E). Capacity = active bedrooms; occupancy metrics shared via `OccupancyService::compute_metrics()`.
- **Deps:** Home/Bedroom/Occupancy repositories, visit/care-plan/MAR repos (dashboard), `UserProvider`, `ReferralRepository`, `AccessPolicy`, `ReferralActivityService`
- **Docs:** `docs/SUPPORTED_LIVING.md`

### `CareSetting` (domain)
- **Purpose:** Allowlist / labels for `jmrs_referrals.care_setting` (`supported_living`, `own_home`, NULL = not specified).
- **Used by:** `ReferralService`, `ReferralFilters`, `OccupancyService`, portal/admin list+edit, CSV export.

### `WorkflowStageService`
- **Purpose:** CRUD; default stage; pipeline counts for dashboard.
- **Deps:** `WorkflowStageRepository`, `ReferralRepository`

### `TransitionPlanningService` / `CareCommencementService` (Phase 3G)
- **Purpose:** Derive Transition Planning readiness from existing LA decision / occupancy / care plan / team / schedule state; explicitly record care commencement (`care_commenced_at`/`by`) and pipeline `transition_planning` → `care_commenced`.
- **Deps:** Pipeline, LA decision repo, occupancy/home/bedroom repos, care plan/team/schedule repos, `ServiceLocationResolver`, `AccessPolicy`, activity, `ReferralService`
- **Used by:** `ReferralViewController`, `PortalController`
- **Notes:** Does not auto-commence on placement or visit. Does not modify `OccupancyService`.

### `PipelineAttentionService` (Phase 3H)
- **Purpose:** Pipeline overview counts, Needs Attention queue, Active Pipeline Queue; waiting-time and internal-target evaluation.
- **Deps:** `ReferralRepository`, `PackageCostRepository`, `AccessPolicy`, `UserProvider`, `PipelineInternalTargets`
- **Used by:** Portal dashboard, WP Admin `DashboardPage`
- **Notes:** Read-only. No hard-coded SLA hours. Support Workers excluded from commercial pipeline surface.

---

## Notifications

### `NotificationService`
- **Purpose:** Domain emails (created, assigned, status, public received/confirmation, interest expressed, package cost sent).
- **Deps:** `EmailNotificationService`, `UserProvider`
- **Notes:** `notify_package_cost_sent($referral, $to_email, $attachment_paths)` uses template `package-cost-sent` and passes optional filesystem attachments. Success = mailer accepted, not delivery proof.

### `EmailNotificationService`
- **Purpose:** Render/send via `wp_mail` and template resolver.
- **Deps:** optional `EmailTemplateResolver`
- **API:** `send($to, $subject, $template, $vars = [], $attachments = [])`. Attachments are optional absolute readable server paths; empty array preserves pre-3E.1 behaviour. Request-supplied paths must never be passed.
---

## Alerts & reports

### `OperationalAlertService`
- **Purpose:** Calculate/filter/sort operational alerts; dashboard formatting.
- **Deps:** Multiple repos (referral, assessment, care plan, reviews, team, schedules, visits, tasks), AccessPolicy, optional med admin repo
- **Used by:** Alerts page (Menu), dashboard, portal dashboard, `ReportService`

### `ReportService`
- **Purpose:** Report payloads and dashboard summary shortcut; Phase 2G.1 Supported Living current-snapshot aggregates; Phase 2G.2 vacancy report; Phase 2G.3 placement movements; Phase 2G.4 visit care-delivery filters.
- **Deps:** `ReportRepository`, `AcquisitionReportRepository`, AccessPolicy, `OperationalAlertService`, `UserProvider`, `OccupancyRepository`
- **Constructed in:** `Menu` (not `Plugin::registerReferralControllers`)
- **Notes:** Estate KPIs reuse `OccupancyRepository::estate_summary()` / `OccupancyService::compute_metrics()`. Care-setting counts reuse Active Clients semantics (`archived_at IS NULL`, status not completed/cancelled). Snapshot metrics are not date-range filtered. Vacancy detail uses `ReportRepository::list_current_vacancies()` (one grouped query) and home filter `jmrs_report_home`; detailed vacancy requires `jmrs_view_reports` + `jmrs_view_homes`. Placement movements use `count_placement_movements_in_range` / `list_placement_movements_in_range` on `jmrs_referral_activity` (`activity.created_at`); assigned-to scope only (historical completed/archived events retained); home filter not applied. Visit care-delivery filters (`jmrs_visit_care_context` / `jmrs_visit_home`) use `VisitDeliveryContext` classification (snapshot for executed; current care setting/occupancy for open; Location Not Recorded for terminal without snapshot) and must not reuse vacancy `jmrs_report_home`. Phase 2G.5 polish: filter fieldsets, snapshot/period badges, empty states, CareSetting label alignment, responsive Reports CSS, UAT checklist. **Phase 3I Acquisition Pipeline:** cohort by `DATE(created_at)`; structured Phase 3 identity via stage-history `change_type=created` (not current `is_pipeline_stage`); includes archived; timing from structured timestamps only; Package Cost / LA aggregates use latest-by-id; CSV section `acquisition_pipeline`.

### `AcquisitionReportRepository`
- **Purpose:** Aggregate SQL for Phase 3I acquisition cohort metrics (no capability checks).
- **Used by:** `ReportService`

### `VisitDeliveryContext`
- **Purpose:** Shared Visit report filter normalisation and SQL predicates for Phase 2G.4.
- **Used by:** `ReportRepository`, `ReportService`, `ReportController` / `ReportExportController` request params
- **Notes:** No per-visit PHP loops; occupancy LEFT JOIN only when Visit filters are active.

---

## Public intake

### `PublicReferralService`
- **Purpose:** Public submit pipeline (spam → create → uploads → notify).
- **Deps:** `ReferralService`, referral repo, `ServiceTypeService`, `ReferralDocumentService`, `NotificationService`
- **Used by:** `PublicReferralController`

---

## Future notes

- Prefer extending these services over adding portal-only domain forks.
- `ReportService` / second `OperationalAlertService` construction in `Menu` duplicates some Plugin graph — known limitation (`PERF-H-008` / Known Limitations).

---

## Related

- [`DEPENDENCY_INJECTION.md`](DEPENDENCY_INJECTION.md)
- [`ARCHITECTURE.md`](ARCHITECTURE.md)
