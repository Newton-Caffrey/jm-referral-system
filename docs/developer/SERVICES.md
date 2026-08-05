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
- **Deps:** `ReferralRepository`, `ReferralDependencyRepository`, activity, AccessPolicy
- **Used by:** List/view retention actions, portal archived display helpers

---

## Documents

### `ReferralDocumentService`
- **Purpose:** Staff upload, public intake upload, download preparation, legacy migration batches, storage counts.
- **Deps:** Doc repo, referral repo, activity, AccessPolicy, `PrivateDocumentStorage`
- **Used by:** Document controllers, public intake, Settings migration, portal view downloads

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
- **Purpose:** Execute/complete visits; manager review; awaiting-review / completed dashboards.
- **Deps:** Visit + referral repos, activity, AccessPolicy, `VisitTaskService`, `MedicationAdministrationService`

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

### `WorkflowStageService`
- **Purpose:** CRUD; default stage; pipeline counts for dashboard.
- **Deps:** `WorkflowStageRepository`, `ReferralRepository`

---

## Notifications

### `NotificationService`
- **Purpose:** Domain emails (created, assigned, status, public received/confirmation).
- **Deps:** `EmailNotificationService`, `UserProvider`

### `EmailNotificationService`
- **Purpose:** Render/send via `wp_mail` and template resolver.
- **Deps:** optional `EmailTemplateResolver`

---

## Alerts & reports

### `OperationalAlertService`
- **Purpose:** Calculate/filter/sort operational alerts; dashboard formatting.
- **Deps:** Multiple repos (referral, assessment, care plan, reviews, team, schedules, visits, tasks), AccessPolicy, optional med admin repo
- **Used by:** Alerts page (Menu), dashboard, portal dashboard, `ReportService`

### `ReportService`
- **Purpose:** Report payloads and dashboard summary shortcut.
- **Deps:** `ReportRepository`, AccessPolicy, `OperationalAlertService`, `UserProvider`
- **Constructed in:** `Menu` (not `Plugin::registerReferralControllers`)

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
