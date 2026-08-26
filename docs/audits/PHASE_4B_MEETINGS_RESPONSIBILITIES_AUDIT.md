# Phase 4B.0 — Meetings, Attendees and Responsibility Model Audit

**Type:** Architecture, database and workflow audit (Phase 4B.0)  
**Baseline commit:** `c697a3931a0df9d1414ee51edd50e4dd8d555ad3` (Phase 4A checkpoint)  
**Product:** 1.4.0 · **Database at audit:** 2.28.0 · **Portal rewrite:** 1.2.2  

**Status:** Design approved and implemented as Phase 4B.1 data foundation (DB **2.29.0**). No user-facing meeting UI in 4B.1. See `docs/uat/UAT_PHASE_4B_1_DATA_FOUNDATION.md`.

---

## 1. Existing architecture findings

### 1.1 Referrals and ownership

| Item | Evidence |
|------|----------|
| Table | `{prefix}jmrs_referrals` (`Tables::referrals_table()`) |
| Owner | `assigned_to` (WP user ID, nullable) |
| Referrer / org | `referrer_name`, `referrer_email`, `referrer_phone`, `referrer_type`, `referrer_organisation` |
| Interest | `interest_expressed_at`, `interest_expressed_by`, `interest_response_method`, `interest_response_recipient`, `interest_email_*` |
| Pipeline | `workflow_stage_id`, `workflow_stage_entered_at`, `next_action_due_at` |
| Care setting | `care_setting` |
| Care commenced | `care_commenced_at`, `care_commenced_by` |
| Archive | `archived_at`, `archived_by`, `archive_reason` |

Ownership for acquisition is **only** `assigned_to`. Reassignment is logged (`assigned` / `reassigned`) and can notify via `NotificationService::notify_referral_assigned`. Support Workers are scoped to their `assigned_to` records via `AccessPolicy::get_assigned_user_constraint()`.

**Do not redefine `assigned_to` in Phase 4B.**

### 1.2 Assessment and assessment scheduling

| Item | Evidence |
|------|----------|
| Table | `{prefix}jmrs_referral_assessments` — **UNIQUE(`referral_id`)** (one clinical assessment row per referral) |
| Assessor | `assessor_user_id` |
| Clinical | `assessment_date`, `outcome`, `summary`, `recommendations`, clinical LONGTEXTs |
| Scheduling | `scheduled_at`, `assessment_location_type`, `assessment_location_name`, `assessment_location_address`, `assessment_contact_name/phone/email`, `scheduling_notes` |
| Services | `AssessmentSchedulingService` (schedule / reschedule / needs-rescheduling + pipeline advance); `ReferralAssessmentService` (clinical save; preserves scheduling fields) |

Location types (`AssessmentScheduling`): `hospital` | `current_care_home` | `own_home` | `other`.

Canonical path:

`interest_required` → **`assessment_to_schedule`** → `assessment_scheduled` → (`assessment_review_required`) → `package_cost_required` …

Scheduling a formal assessment advances `assessment_to_schedule` → `assessment_scheduled`. There is **no** multi-attendee model; external contact is free-text snapshot fields on the assessment row.

### 1.3 Care teams

| Item | Evidence |
|------|----------|
| Table | `{prefix}jmrs_care_team` |
| Link | `referral_id`, optional `care_plan_id`, `user_id` |
| Roles | `primary_carer`, `secondary_carer`, `relief_carer`, `nurse`, `assessor`, `coordinator` |
| Status | `active` / `inactive` |

This is **post-intake operational care delivery**, not acquisition meetings or client champion / transition lead.

### 1.4 Users and roles

| Role | Typical acquisition relevance |
|------|-------------------------------|
| JM Administrator | Full |
| Referral Manager | Full commercial + homes |
| Care Coordinator | Edit / assign; commercial gates; no delete/archive/override |
| Assessor | Edit referrals / schedule assessment; **not** commercial gates / Management Dashboard |
| Support Worker | View + execute; **scoped to `assigned_to`**; no edit |

Assignable users: capability `jmrs_view_referrals`. Assessment-eligible: `jmrs_edit_referrals` (`UserProvider`).

### 1.5 Occupancy / homes / bedrooms

| Table | Role |
|-------|------|
| `jmrs_homes` | Property; `manager_user_id` |
| `jmrs_bedrooms` | Capacity |
| `jmrs_occupancies` | Placement; `created_by` / `ended_by`; move-in/out dates |

Placement is created via `OccupancyService::place_resident()` (`MANAGE_OCCUPANCIES`). Distinct from care commencement and from any “transition lead” person field (none exists).

### 1.6 Activity and stage history

| Mechanism | Table / class | Notes |
|-----------|---------------|-------|
| Referral activity | `jmrs_referral_activity` + `ReferralActivityService` | Free-text `action` + `description`; rich existing action vocabulary for assessment / package / LA / archive |
| Stage history | `jmrs_referral_stage_history` | `change_type`: `created` \| `transition` \| `override` |

No meeting / attendee / champion / transition-lead activity types today.

### 1.7 Access and portal

- Commercial-style gates (interest, package cost, LA decision, not proceeding, commence care): JM Admin / Referral Manager / Care Coordinator / WP admin — not Assessor / Support Worker.
- Assessment scheduling mutate: `can_mutate_referral` (Assessor allowed).
- Portal: referral workspace + clinical dispatcher; **no** meeting / champion / transition-lead routes.
- Management Dashboard (Phase 4A): read-only; `client_initials` only (`ManagementClientDisplay`).

### 1.8 Migrations and email

- `Migrator::DB_VERSION = '2.28.0'`; pattern = bump version → `Tables::create()` (dbDelta) → version-gated comments / side effects; **no invented backfills**.
- Latest relevant: `2.24.0` assessment scheduling columns; `2.28.0` care commenced columns.
- Email: `NotificationService` / `EmailNotificationService` for assignment, interest, package cost, public intake — **no** assessment-appointment or meeting emails.

### 1.9 Archive / delete

- Archive-first soft delete on referrals; mutations blocked while archived.
- Permanent delete blocked by dependency summary (notes, documents, assessment, care plan, care team, schedules, visits, medications, non-bootstrap activity).
- Known gap: package costs, LA decisions, stage history, and occupancies are **not** listed as permanent-delete blockers today — any new meeting tables must be designed into retention/delete policy explicitly.

---

## 2. Overlapping concepts

| Proposed Phase 4B concept | Closest existing artifact | Overlap risk | Gap |
|---------------------------|---------------------------|--------------|-----|
| Pre-assessment / commissioner meeting | Assessment appointment on `jmrs_referral_assessments` | High if Stage 2 / scheduling UI conflated | No meeting entity |
| Formal assessment appointment | `AssessmentSchedulingService` + assessment row | Already owned — must stay distinct | Single assessor only |
| Internal attendee | Care team `user_id`; visit `assigned_user_id` | Medium if care-team roles reused for meetings | No meeting attendees |
| External participant | `assessment_contact_*`; `referrer_*` | Medium — free-text single contact | No participant list / entity |
| Assessor | `assessor_user_id`; care-team role `assessor` | Naming collision | Role clarity required in UI |
| Referral owner | `assigned_to` | Must remain canonical owner | — |
| Client champion | — / care-team `coordinator` | High if “coordinator” overloaded | Does not exist |
| Transition lead | `assigned_to`; `TransitionPlanningService` readiness panel; occupancy `created_by` | High if owner/placement creator treated as lead | No lead person field |
| Organisation | `referrer_organisation`; package `recipient` | Low | Meeting-specific org on participant |

**Explicit non-existence:** no PHP schema/code for `meeting`, `attendee`, `champion`, or `transition_lead` entities.

---

## 3. Business concept definitions (proposed)

### A. Pre-assessment / commissioner meeting

A **structured multi-party meeting** held after interest is taken forward and **before or around** the formal clinical assessment process. Typical participants: LA representative, social worker, commissioner, client, family, advocate, and J&M staff.

**Distinct from** the formal assessment appointment (clinical assessor visit stored on `jmrs_referral_assessments`).

### B. Internal attendee

A WordPress / JMRS user invited to or present at a meeting (`user_id` + meeting role + attendance status).

### C. External participant

A non-JMRS person recorded for a meeting: name, professional role, organisation, optional email/telephone, category, attendance status. **Not** a WP user. PII — minimise and scope.

### D. Client champion

J&M staff member responsible for **relationship continuity and progression** of the referral through acquisition (chase, coordinate stakeholders, keep momentum).

| Differs from | How |
|--------------|-----|
| Referral owner (`assigned_to`) | Owner is system assignee / queue owner; champion is relationship lead (may be same person, must not be forced identical) |
| Assessor | Clinical assessment lead for the formal assessment appointment |
| Care Coordinator (role) | Capability role; champion is a **referral responsibility**, not a WP role rename |
| Transition lead | Champion spans acquisition; transition lead focuses on post-approval placement handover |

### E. Transition lead

J&M staff member responsible for **coordinating authority approval → placement / care commencement**.

| Differs from | How |
|--------------|-----|
| Referral owner | May still own the record; transition lead is the handover coordinator |
| Champion | Different phase emphasis; may be same user |
| Occupancy creator (`created_by`) | Placement mutation actor ≠ designated lead |
| Care Coordinator | Role vs per-referral responsibility |

---

## 4. Recommended meeting model

### Options assessed

#### Option A — `jmrs_referral_meetings` + `jmrs_referral_meeting_attendees`

| | |
|--|--|
| **Advantages** | Clear domain boundary; multiple meetings per referral; no collision with UNIQUE assessment row; mirrors package-cost / LA-decision sibling-table style |
| **Disadvantages** | New tables; separate UI; cannot reuse assessment schedule form as-is |
| **Migration** | Low–medium: two new tables via dbDelta; no backfill |
| **Query** | Straightforward joins by `referral_id` / `meeting_id` |
| **Access** | Same referral visibility + new caps or reuse edit/mutate commercial gate |
| **Audit** | Activity log + optional status timestamps |
| **Extensibility** | `meeting_type` supports future types without a generic events bus |
| **Overlap risk** | **Low** if type ≠ assessment appointment and Stage 2 semantics stay explicit |

#### Option B — Generic appointments / events model

| | |
|--|--|
| **Advantages** | One calendar-like model for future appointment kinds |
| **Disadvantages** | Over-general for current product; risks absorbing assessment scheduling; larger design surface; harder AccessPolicy stories |
| **Migration** | Higher |
| **Overlap risk** | **High** with assessment scheduling |

#### Option C — Extend `jmrs_referral_assessments` / care team

| | |
|--|--|
| **Advantages** | Fewer tables |
| **Disadvantages** | UNIQUE(`referral_id`) blocks multiple meetings; mixes clinical assessment with commissioner meetings; care team is wrong lifecycle |
| **Overlap risk** | **Critical — reject** |

### Recommendation: **Option A**

Dedicated referral meetings + attendees tables. Support **multiple meetings per referral**. Keep assessment scheduling untouched on `jmrs_referral_assessments`.

---

## 5. Proposed minimum meeting fields (Phase 4B)

### 5.1 Table: `jmrs_referral_meetings` (minimum)

| Field | Include? | Notes |
|-------|----------|-------|
| `id` | Yes | PK |
| `referral_id` | Yes | FK-style index; not UNIQUE |
| `meeting_type` | Yes | Controlled — see below |
| `status` | Yes | Controlled |
| `scheduled_at` | Yes | Single datetime (match assessment pattern) **or** date + start time; prefer one `scheduled_at` + optional `scheduled_end_at` |
| `scheduled_end_at` | Optional | Useful; not mandatory for MVP |
| `location_type` | Yes | Controlled |
| `location_name` | Yes | Nullable text |
| `location_address` | Optional | Nullable; omit long clinical notes |
| `online_meeting_url` | Optional | Defer unless required |
| `title` / purpose | Optional short | Prefer short `purpose` VARCHAR; avoid LONGTEXT agenda |
| `outcome` | Optional short | Controlled / short text; not clinical narrative |
| `notes` | Defer or short | Prefer operational note only; **no** clinical narrative |
| `created_by` / `updated_by` | Yes | WP user |
| `created_at` / `updated_at` | Yes | |
| `cancelled_at` / `completed_at` | Yes | Soft lifecycle stamps |
| `timezone` | No for MVP | Use WP/site timezone like assessment |
| `archived_at` | No unless referral archive already sufficient | Prefer inherit referral archive |

### 5.2 Controlled values (proposed)

**`meeting_type`**

- `pre_assessment` — commissioner / pre-assessment meeting (primary Phase 4B type)
- Reserve (do not implement UI yet): `other` only if needed; **do not** put formal clinical assessment here

**`status`**

- `draft` | `scheduled` | `completed` | `cancelled`

**`location_type`**

- Reuse assessment vocabulary where sensible: `hospital` | `current_care_home` | `own_home` | `other`
- Add if needed: `office` | `online` (product decision)

### 5.3 Explicitly exclude from MVP

- Clinical summaries / recommendations
- Prototype JSON structures
- Forced 1:1 with assessment row
- Fabricated seed attendees

---

## 6. Recommended attendee model

### Shared table vs split tables

**Recommend: one table `jmrs_referral_meeting_attendees` with participant kind**

| Field | Notes |
|-------|-------|
| `id` | PK |
| `meeting_id` | Index |
| `participant_kind` | `internal` \| `external` |
| `user_id` | Nullable; required when internal |
| `display_name` | Required for external; optional snapshot for internal |
| `professional_role` | External (and optional internal meeting role label) |
| `organisation` | External mainly |
| `email` / `phone` | External optional; minimise |
| `participant_category` | e.g. `la_officer`, `social_worker`, `commissioner`, `client`, `family`, `advocate`, `jm_staff`, `other` |
| `meeting_role` | Short label for “role in this meeting” |
| `attendance_status` | `invited` \| `confirmed` \| `attended` \| `absent` \| `declined` |
| `sort_order` | Small int |
| `created_at` / `updated_at` | |

**Why one table:** one query for dashboard “who we are meeting”; consistent attendance status; simpler UI.

**Safeguards**

- UNIQUE suggested: (`meeting_id`, `user_id`) where `user_id` NOT NULL; for external, soft de-dupe by name+org (application-level)
- Deleted WP users: keep `display_name` snapshot; null-safe joins
- Privacy: external PII only on authorised referral views — **never** on Management Dashboard beyond initials for **client**; external names OK for authorised staff on referral workspace / management stage columns that require “who we are meeting” (management audience is senior staff — still minimise phone/email on Management Dashboard)

**Reject:** stuffing attendees into care team.

---

## 7. Champion and transition-lead model

### Option A — Columns on `jmrs_referrals`

`champion_user_id`, `transition_lead_user_id`

| | |
|--|--|
| Pros | Simple reporting; easy dashboard joins; clear current holder |
| Cons | Weak history unless activity log; two more nullable columns |
| AccessPolicy | Treat like assignment fields — commercial mutate roles |
| Confusion risk | Low if labels explicit |

### Option B — `jmrs_referral_responsibilities`

Typed rows: `owner` (mirror only — **do not replace `assigned_to`**), `champion`, `transition_lead`

| | |
|--|--|
| Pros | Extensible; can store effective dates; historical rows possible |
| Cons | More join complexity; risk of dual source of truth if `owner` duplicated |
| History | Stronger if designed with `effective_from` / `ended_at` |

### Option C — Extend care team

| | |
|--|--|
| Pros | Existing table |
| Cons | Wrong domain (clinical ops); role name collisions; active care-plan coupling |
| Verdict | **Reject** |

### Recommendation

**Phase 4B.1–4B.3 pragmatism: Option A columns on referrals** for current champion and transition lead, with **mandatory activity-log events** on change.

If historical “who was champion last quarter” becomes a reporting requirement soon after, add Option B in a later sub-phase **without** duplicating owner — keep `assigned_to` as sole owner source.

**Do not** store `owner` in a responsibilities table as a second write path.

---

## 8. Audit / history design

### Prefer existing `jmrs_referral_activity`

Add action strings (examples):

- `meeting_created`, `meeting_updated`, `meeting_scheduled`, `meeting_rescheduled`, `meeting_cancelled`, `meeting_completed`
- `meeting_attendee_added`, `meeting_attendee_removed`
- `champion_assigned`, `champion_changed`
- `transition_lead_assigned`, `transition_lead_changed`

Descriptions should include referral-safe identifiers (referral number, meeting id, user display names) — **not** unnecessary clinical content.

### Stage history

Only when a **canonical pipeline transition** occurs. Meeting create/complete alone must **not** invent stage history rows without an approved stage rule.

### Dedicated meeting history table

**Defer.** Activity log + meeting row timestamps (`cancelled_at`, `completed_at`, `updated_at`) are enough for Phase 4B unless compliance demands field-level diffs.

### No destructive overwrite without evidence

Updates mutate the meeting row in place with `updated_by` / `updated_at` and activity entries; cancellations set `cancelled_at` + status rather than hard-delete by default.

---

## 9. Stage 2 semantic recommendation

### Current state (Phase 4A — keep until approved change)

| Visual Stage 2 | Canonical |
|----------------|-----------|
| Label: **Appointment to Arrange** | `assessment_to_schedule` only |

Boss prototype “Appointment Set” ≠ current JMRS meaning.

### Answers (design position — **no stage change in this audit**)

| Question | Recommendation |
|----------|----------------|
| What event enters Stage 2 today? | Interest taken forward → pipeline to `assessment_to_schedule` |
| Does Stage 2 mean commissioner meeting, assessment appointment, or both? | **Today: assessment appointment still needed.** Commissioner meeting is a **parallel operational artefact**, not the definition of Stage 2 |
| What is “Appointment Set”? | **Do not** equate to “any meeting row exists.” Prototype meaning ≈ multi-party meeting booked. JMRS accuracy requires either keeping “to arrange” for assessment scheduling **or** introducing a **new canonical stage** if product insists Stage 2 = commissioner meeting |
| Meeting cancelled? | Meeting `cancelled`; **do not** auto-rewind pipeline unless product defines a rule |
| Multiple meetings? | Allowed; dashboard shows **next upcoming scheduled** pre-assessment meeting, else latest non-cancelled |
| Auto-advance on meeting complete? | **No** — keep controlled transitions (manual / existing service gates) |
| Repurpose `assessment_to_schedule`? | **Forbidden.** That slug means formal assessment still needs scheduling |

### If semantic accuracy requires a new stage (separate approval)

Possible future canonical insert (illustrative only — **not implementing**):

- e.g. `pre_assessment_meeting` between interest and assessment scheduling  
- Visual Stage 2 would then map to that stage; assessment scheduling remains Stage 3 entry  

This is a **product + migration decision**, not part of Phase 4B.0 approval of tables alone.

**Interim dashboard rule (when meetings exist, before any stage remap):** Stage 2 panel continues to list `assessment_to_schedule` referrals; optionally show linked upcoming pre-assessment meeting as **enrichment**, never as proof that assessment is booked.

---

## 10. Dashboard integration design (future — not implement now)

### Stage 2 columns (future)

- Referral number, client **initials**, funding authority  
- Who J&M is meeting (external participant summary)  
- Participant roles (short)  
- Scheduled date/time, location  
- J&M attendees (names of internal users)  
- Meeting status  
- Owner (`assigned_to`)  
- Champion (when present)  

Privacy: continue `ManagementClientDisplay` initials; no full client names; avoid external phone/email on management board.

### Stage 3 additions (future)

- Assessment lead (`assessor_user_id`)  
- Client champion  
- Assessment date / status / outcome (existing fields)  

### Stage 6 additions (future)

- Transition lead  

---

## 11. UI and capability design

### Entry points (proposed)

| Surface | Role |
|---------|------|
| Referral workspace section / **Meetings** panel | Primary CRUD |
| Quick action “Schedule pre-assessment meeting” | When stage ≥ interest taken forward |
| Management Dashboard | Read-only deep link to referral meetings section |
| WP Admin | Optional parity later; portal-first preferred |

### Permission mapping (proposed)

| Action | JM Admin | Referral Manager | Care Coordinator | Assessor | Support Worker | WP Admin |
|--------|----------|------------------|------------------|----------|----------------|----------|
| View meetings (on visible referral) | Yes | Yes | Yes | Yes* | No† | Yes |
| Create / edit / cancel / complete meeting | Yes | Yes | Yes | No‡ | No | Yes |
| Manage attendees | Yes | Yes | Yes | No‡ | No | Yes |
| Assign champion / transition lead | Yes | Yes | Yes | No | No | Yes |

\* Assessor may view if they can view the referral (assessment context).  
† Support Worker remains scoped and should not gain acquisition meeting manage rights merely as “staff”. Prefer **deny** meetings manage; view only if product later requires and still scoped — default **deny**.  
‡ Assessor focuses on formal assessment appointment; do not grant commissioner-meeting mutate by default (align with commercial-gate pattern). Product may reopen.

Reuse capability style: either new `jmrs_manage_referral_meetings` or gate behind existing commercial mutate (interest/package pattern) + `EDIT_REFERRALS`. Prefer an **explicit capability** for clarity.

---

## 12. Notification recommendation

| Event | Phase 4B | Notes |
|-------|----------|-------|
| Meeting scheduled (internal assignees / attendees) | **Optional** | Mirror assignment email pattern |
| Meeting rescheduled / cancelled (internal) | **Optional** | |
| Internal attendee added | **Deferred** or optional digest | Noise risk |
| Champion / transition lead assigned | **Optional** | Same as referral assigned |
| External participant emails | **Deferred — requires explicit approval + privacy review** | Do not ship in 4B without sign-off |

**Required in Phase 4B core:** none strictly required for MVP if activity log + UI are solid. Prefer ship meetings CRUD before email.

---

## 13. Migration strategy

1. Bump `Migrator::DB_VERSION` from **2.28.0 → 2.29.0** (proposed target; **do not change yet**).  
2. Add `Tables::create_*` for meetings + attendees (+ referral columns for champion / transition lead).  
3. Version gate comment block only; **no backfill**.  
4. Idempotent: dbDelta safe re-run.  
5. Rollback: reverse deploy + leave empty tables (standard JMRS — no destructive drop in migrator).  

### Legacy-data policy

| Data | Policy |
|------|--------|
| Existing referrals | Zero meetings; null champion / transition lead |
| Assessment scheduling rows | **Never** auto-convert to commissioner meetings |
| Attendees / champions / leads | **Never** fabricate |

---

## 14. Proposed future DB version

**`2.29.0`** — first schema release that introduces meetings (+ attendees) and responsibility columns (or responsibilities table if Option B chosen later).

Product / rewrite versions remain on their own cadence (not part of this audit change).

---

## 15. Legacy-data policy

Restated: empty by default; no assumption that `assessment_contact_*` equals a meeting participant list; no assumption that `assigned_to` equals champion or transition lead.

---

## 16. Retention / deletion behaviour

| Event | Proposed behaviour |
|-------|--------------------|
| Referral archived | Meetings read-only; no new meetings; follow referral mutate block |
| Referral restored | Meetings editable again per caps |
| Referral permanently deleted | **Must** delete or block on meeting/attendee rows (add to dependency summary / cascade delete in retention service) — fix the commercial-history gap pattern for new tables |
| WP user deleted | Keep attendee/responsibility snapshots; null `user_id` or retain ID with broken-user label |
| External correction request | Edit/remove external attendee row; activity log |
| Meeting cancelled | Soft cancel (`status=cancelled`, `cancelled_at`); retain row |

Archive-first remains the JMRS default.

---

## 17. Security / privacy findings

- Enforce referral `can_view` / `can_mutate` + proposed meeting capability; Support Worker deny for manage.  
- Prevent IDOR on `meeting_id` (always join `referral_id` + access check).  
- External PII: minimise on Management Dashboard (names/roles/org OK for senior board; hide email/phone there).  
- Client privacy: Management Dashboard initials only (Phase 4A).  
- Future forms: nonces, capability checks, sanitise URLs, escape output.  
- No external email without approval.  
- Audit via activity log.

---

## 18. Reporting possibilities

| Metric | Reliable after Phase 4B data? | Needs more definition? |
|--------|-------------------------------|------------------------|
| Pre-assessment meetings scheduled | Yes (`status` + `scheduled_at`) | — |
| Meetings attended | Partial | Need consistent `attendance_status=attended` discipline |
| Meetings by staff member | Yes (internal attendees / created_by) | — |
| Assessments led | Already (`assessor_user_id`) | — |
| Clients championed | Yes if `champion_user_id` populated | Definition of “active champion” period |
| Placements led | Partial | Transition lead ≠ occupancy creator; define metric |
| Meeting → assessment conversion | Partial | Need business rule: which meeting counts; time windows |
| Assessment → costing conversion | Existing pipeline stages | Unchanged |

---

## 19. Implementation phases (proposed)

| Phase | Scope | Checkpoint |
|-------|-------|------------|
| **4B.1** | DB 2.29.0, Tables, repositories, no UI | Migration UAT empty tables |
| **4B.2** | Meeting CRUD + attendees services/handlers | Referral meeting create/edit/cancel |
| **4B.3** | Champion + transition lead columns + activity | Assign/change on referral |
| **4B.4** | Referral workspace Meetings UI | Portal UAT |
| **4B.5** | Management Dashboard enrichment (initials preserved) | Dashboard UAT |
| **4B.6** | Optional internal notifications | Email UAT |
| **4B.7** | Reporting hooks + full UAT pack | Sign-off |

**Do not** start 4B.1 until this audit is approved and Stage 2 semantic decisions in §20 are answered.

---

## 20. Business decisions still required

1. Is Visual Stage 2 permanently “assessment to schedule”, or will a **new canonical stage** for pre-assessment meetings be approved later?  
2. Does completing a pre-assessment meeting ever **auto-advance** pipeline? (Recommend no.)  
3. May Assessor create/edit commissioner meetings? (Recommend no.)  
4. May Support Worker view meetings on assigned referrals? (Recommend no for MVP.)  
5. Are external participant emails in or out of Phase 4B? (Recommend out.)  
6. Champion / transition lead: confirm **Option A columns** vs delay for responsibilities table.  
7. Location types: add `office` / `online`?  
8. Management Dashboard: show external participant **names** (yes for management) vs phones (no)?  
9. Permanent-delete policy for meeting rows: cascade vs block.  
10. Exact label set for `participant_category` and attendance statuses.

---

## 21. Risks

- Silently treating assessment appointments as commissioner meetings.  
- Overloading `assigned_to`, care-team roles, or `assessment_contact_*`.  
- Stage label “Appointment Set” reintroduced without canonical meaning.  
- PII sprawl (external emails on dashboards / exports).  
- Auto-transition side effects without staff control.  
- Permanent delete leaving orphan meeting rows (retention gap).  
- Dual owner sources if responsibilities table includes `owner`.

---

## 22. Blockers

| Blocker | Severity |
|---------|----------|
| Product decision on Stage 2 / possible new canonical stage | **Blocks 4B.5 stage semantics**; does **not** block 4B.1–4B.4 meeting CRUD |
| External email / privacy approval | Blocks notification to externals only |
| None for pure schema design of Option A | — |

No technical blocker prevents approving the **Option A** data model and Option A responsibility columns, provided Stage 2 is not silently remapped.

---

## 23. Final verdict

**PHASE 4B DESIGN READY FOR APPROVAL**

Recommended design summary:

1. **Meetings:** `jmrs_referral_meetings` + `jmrs_referral_meeting_attendees` (Option A), multiple per referral, distinct from assessment scheduling.  
2. **Responsibilities:** `champion_user_id` + `transition_lead_user_id` on referrals; keep `assigned_to` as owner.  
3. **Audit:** extend `ReferralActivityService` actions; no dedicated meeting history table in MVP.  
4. **Stages:** do **not** repurpose `assessment_to_schedule`; resolve Stage 2 product semantics before dashboard remap.  
5. **DB target:** **2.29.0** later; versions remain **1.4.0 / 2.28.0 / 1.2.2** until implementation is approved.  
6. **Notifications:** optional internal; external deferred.  

Await explicit approval before any Phase 4B.1 implementation.

---

## Evidence index (primary)

| Area | Path |
|------|------|
| Schema / dbDelta | `src/Database/Tables.php`, `src/Database/Migrator.php` |
| Pipeline stages | `src/Pipeline/PipelineStage.php`, `src/Pipeline/VisualStageMap.php` |
| Assessment scheduling | `src/Assessment/AssessmentSchedulingService.php`, `ReferralAssessmentRepository.php` |
| Care team | `src/CareTeam/CareTeamService.php` |
| Access | `src/Permissions/AccessPolicy.php`, `Capabilities.php`, `Roles.php` |
| Activity | `src/Referral/ReferralActivityService.php` |
| Transition panel | `src/Transition/TransitionPlanningService.php` |
| Management privacy | `src/Pipeline/ManagementClientDisplay.php` |
| Retention | `src/Referral/ReferralRetentionService.php` (dependency summary) |
| Email | `src/Notifications/` (NotificationService / EmailNotificationService) |
