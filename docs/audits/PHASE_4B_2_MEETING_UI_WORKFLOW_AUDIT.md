# Phase 4B.2.0 — Meetings and Attendees UI / Workflow Audit

**Type:** Architecture, UI and workflow audit only (no implementation)  
**Baseline commit:** `b930611c00e5d6651bd4a36da6618b4148d5b0dc` (Phase 4B.1 checkpoint)  
**Product:** 1.4.0 · **Database:** 2.29.0 · **Portal rewrite:** 1.2.2 (baseline) → **1.2.3** (Phase 4B.2.1)

**Status:** Audit approved; Phase 4B.2.1–4B.2.3 checkpointed. Phase 4B.2.4 external participant management: rewrite **1.2.6**; Batches 1–3 manual staging UAT **PASS** (2026-08-27); Batch 4 (cancelled/archived/scheduled-removal) **NOT RUN — CODE REVIEWED** — **PARTIAL MANUAL UAT ACCEPTED** with **NON-BLOCKING DOCUMENTED UAT RISK**. Phase 4B.2.5 final polish: Product **1.4.0** · DB **2.29.0** · rewrite **1.2.6**; no new routes/schema; focused manual staging UAT **PASS** (2026-08-27). 4B.2.4 Batch 4 residual risk remains documented (4B.2.5 cancelled-route smoke does not replace Batch 4).

### Phase 4B.2.5 implementation notes (final polish)

| Topic | Decision / fact |
| --- | --- |
| Versions | Product **1.4.0** · DB **2.29.0** · rewrite **1.2.6** (unchanged) |
| Focused UAT | **PASS** 2026-08-27 (meeting **19** on referral **7**; synthetic `Phase 4B.2.5%` data cleaned; referral 7 retained) |
| Defects fixed | Attendance-correction sticky identity; reschedule no-op; draft→schedule uses `meeting_scheduled`; a11y/responsive polish |
| Manual confirms | Identical reschedule no activity; correction identity visible after validation failure; warning count 2; ~375px PASS |
| Explicit non-goals | No new routes/capabilities; no emails; no schema; no dashboard/workflow changes; no attendee-based access |

### Phase 4B.2.4 implementation notes (external participants)

| Topic | Decision / fact |
| --- | --- |
| Routes | `referral_meeting_external_attendee_new` / `_edit` / `_remove` under `…/attendees/external/…` and `…/attendees/{id}/external/…` |
| Rewrite | **1.2.6** (version-gated flush only) |
| Categories | Existing `MeetingAttendee` allowlist only (no new constants) |
| Fields | Required: display name, category, meeting role, attendance; optional: professional role, organisation, email, telephone |
| Contacts | Rendered only when `can_view_referral_meeting_contacts`; omitted from Assessor markup entirely |
| Duplicates | Same-name participants allowed; no uniqueness rule |
| Lifecycle | Same matrix as internal attendees |
| Soft warning | Combined internal + external non-final count (unchanged service method) |
| Explicit non-goals | No invitations/emails; no Management Dashboard; no workflow-stage changes; no 4B.2.5 polish |

**Out of scope for 4B.2:** Champion / Transition Lead UI (→ 4B.3), Management Dashboard meeting widgets, assessment scheduling, care-team assignments, ownership (`assigned_to`), canonical pipeline / VisualStageMap, emails, schema changes.

---

## 1. Existing referral-workspace architecture

### 1.1 Portal shell (actual)

| Component | Path / fact |
|-----------|-------------|
| Router | `src/Portal/PortalRouter.php` — rewrite version **`1.2.2`** |
| Base path | `PortalSettings` default **`staff-portal`** |
| Query vars | `jmrs_portal`, `jmrs_portal_route`, `jmrs_portal_id`, `jmrs_portal_entity` |
| URLs | `src/Portal/PortalUrls.php` |
| Controller hub | `src/Portal/PortalController.php` |
| Navigation | `src/Portal/PortalNavigation.php` (`REFERRAL_RELATED_ROUTES`) |
| Assets | `src/Portal/PortalAssets.php` → `assets/css/portal.css`, `assets/js/portal.js` |
| Retention POST | `PortalRetentionHandler::maybe_handle()` runs **before** route match |

### 1.2 Referral workspace is not tabbed

Hub route key: **`referral`**.  
Pattern: `/referrals/([0-9]+)/?$` → `PortalUrls::referral($id)`.  
Template: `templates/portal/referrals/view.php` — **single long-scroll page**, no tabs.

**Quick actions** (`jmrs-portal-quick-actions--referral` via `build_referral_quick_actions()`): Edit, Assessment, Care Plan, Visit, Medication, Care Team, Place in Supported Living — all gated by capability flags.

**Pipeline / commercial panels** (POST to current URL): assessment scheduling, express interest, package cost, LA decision, non-proceeding, transition planning — shared partials under `templates/referrals/partials/`.

**Stacked sections** (`jmrs-portal-section jmrs-portal-panel`): Summary → Client → care-setting/placement → Referrer → Care requirements → Documents → Assessment (readonly + link) → Care Plan → Care team → Visits → Schedules → Medications → **Activity Timeline** → Archive.

### 1.3 Nested resource convention (preferred pattern for meetings)

Clinical mutations use **separate authorised pages** under the referral, not tabs:

| Resource | List/summary on view | Mutate routes |
|----------|----------------------|---------------|
| Care team | Section + “Add Team Member” | `/referrals/{id}/care-team/new/`, `…/care-team/{entity}/edit/` |
| Medications | Section | `…/medications/new/`, `…/medications/{entity}/edit/` |
| Schedules / visits | Sections | `…/schedules/…`, `…/visits/…` |
| Assessment form | Section + link | `…/assessment/` (scheduling stays on view panel) |
| Occupancy | Placement section | **`/occupancy/…`** (outside `/referrals/`) |

Entity id uses `jmrs_portal_entity`. Success redirects typically: `wp_safe_redirect(add_query_arg(…, PortalUrls::referral($id)))` + GET flash helpers.

### 1.4 Access and archive (actual)

- Inaccessible / missing referrals → generic **404** (no existence leak).
- `can_mutate_referral` false when archived → blocks edit/schedule/package/clinical mutate.
- Support Worker: `should_scope_to_assigned()` — view only assigned referrals; no EDIT → cannot mutate.
- Assessor: unrestricted referral view/edit for clinical work; **denied** `can_express_interest` / package / meetings-manage helper.
- Activity: `ReferralActivityRepository::get_by_referral_id($id, 25)` → `<ol class="jmrs-portal-timeline">` with `mysql2date`.

### 1.5 Phase 4B.1 foundation available to UI

Services (no routes yet): `ReferralMeetingService`, `MeetingAttendeeService`, `can_manage_referral_meetings()`.  
Tables: `jmrs_referral_meetings`, `jmrs_referral_meeting_attendees`.  
**Important:** Service-layer `update()` is **transition-permissive** (can move completed → draft). UI must enforce a stricter FSM; optional service hardening can follow in a polish sub-phase.

### 1.6 Safest addition strategy

Do **not** redesign the workspace into tabs. Follow care-team pattern:

1. Compact **Meetings** summary section on the referral view (next meeting + counts + link).  
2. Dedicated nested portal pages for list/detail/create/edit/attendees.  
3. Optional quick action “Meetings” / “Schedule meeting” when `can_manage_referral_meetings`.  
4. Keep assessment-scheduling panel untouched and visually separate.

---

## 2. Recommended UI location

### Options assessed

| Option | Summary | Usability | Consistency | Routes | Controllers | Permissions | Mobile | Maintenance | Overcrowd risk |
|--------|---------|-----------|-------------|--------|-------------|-------------|--------|-------------|----------------|
| **A** Full Meetings tab/section with all CRUD on the referral view | Everything on one page | Poor once multi-meeting + attendees | Conflicts with care-team pattern | Low | High (POST sprawl on `render_referral_view`) | Same gates | Heavy forms on small screens | Hard | **High** — view already long |
| **B** Summary on referral view + separate authorised meeting pages | List/next meeting on hub; work on nested pages | Best | Matches care team / meds / visits | Medium | Medium, isolatable handler | Clear manage vs view | Forms on dedicated pages | Good | **Low** |
| **C** Only separate pages, no summary on view | Discoverability weak | Fair | Partial | Medium | Medium | OK | OK | OK | Low on view, high navigation cost |
| **D** Assessment-scheduling-style panel only | Confuses acquisition meetings with formal assessment | Poor | Wrong metaphor | Low | Medium | Commercial gate | OK | Couples wrong domains | Medium + **product confusion** |

### Recommendation: **OPTION B**

- Summary section on `templates/portal/referrals/view.php` (like Care team).  
- Nested routes under `/referrals/{id}/meetings/…` (like care-team).  
- Supports **multiple meetings per referral** without overcrowding.  
- Keeps assessment scheduling panel separate.  
- Management Dashboard and Champion/Transition Lead stay out of 4B.2.

---

## 3. Proposed user journey

1. Open referral workspace (`PortalUrls::referral($id)`).  
2. See **Meetings** summary (next upcoming / latest relevant + count) → **View meetings** / **Add meeting**.  
3. Open meeting list page.  
4. Create meeting as **draft** or **scheduled** (both allowed; draft not mandatory).  
5. On detail/edit, add **internal** attendees (assignable users).  
6. Add **external** participants (synthetic-safe fields).  
7. Edit meeting details (type, location, purpose, schedule fields).  
8. Schedule or reschedule (`reschedule` / scheduled create).  
9. Update attendance statuses (invited → confirmed / attended / absent / declined).  
10. Complete or cancel.  
11. Activity appears on referral Activity Timeline (existing log methods).  
12. Return to referral workspace via breadcrumb / “Back to referral”.

### Lifecycle clarifications (recommended product rules)

| Question | Recommendation |
|----------|----------------|
| Create directly as scheduled? | **Yes**, if `scheduled_at` (+ valid type/location rules) provided |
| Draft required first? | **No** |
| Attendees before scheduling? | **Yes** (draft or scheduled) |
| Edit completed meetings? | **Limited:** attendance corrections + short outcome only; no free status rewind in UI |
| Cancelled restore? | **No reopen.** Duplicate as new meeting if needed |
| Accidentally completed → reopen? | **No normal reopen.** Manager correction path deferred; prefer new meeting + activity note if essential. Do **not** expose `update(status=draft)` in UI even though service currently allows it |
| Destructive hard-delete of meetings? | **No** in 4B.2. Cancel retains rows; permanent referral delete cascade already exists in retention |

Prefer retained audit history over destructive editing.

---

## 4. Meeting-list design

### Page

Route: list under referral (see §11). Title: “Meetings — {referral number}”.

### Columns (desktop table / mobile cards)

| Field | Notes |
|-------|-------|
| Type | Label from `ReferralMeeting::type_labels()` |
| Status | Text badge (not colour alone) |
| Scheduled date / time | From `scheduled_at` via `mysql2date` (site conventions) |
| Location | Type label + short name; **no URL** in list |
| Internal count | Count only |
| External count | Count only — **no names/emails/phones in list** |
| Created by | Display name if resolvable |
| Updated | `updated_at` |
| Actions | View / Edit (if allowed) |

### Organisation

**Single list, default filter “Active”** (draft + scheduled), with filter chips: Upcoming · Draft · Completed · Cancelled · All.

- **Default sort:** upcoming first (`scheduled_at ASC` for future scheduled), then drafts by `updated_at DESC`, then completed/cancelled by date DESC — or simple: repository `list_by_referral` order with client-side/section grouping in the template. Prefer server-side grouping into Upcoming / Draft / Past (completed+cancelled collapsed by default).  
- **Pagination:** Optional if > 20; MVP can show all grouped (referrals rarely have dozens).  
- **Empty state:** Use `templates/portal/partials/empty-state.php` pattern + “Add meeting” CTA when manage allowed.  
- **Archived referral:** Read-only list; no Add/Edit.  
- **Next-meeting summary:** On referral view section: `find_next_upcoming_for_referral` else `find_latest_relevant_for_referral`.  
- **Cancelled:** Collapsed under “Cancelled” by default.  
- **PII:** List never shows external email/telephone or online URL.

---

## 5. Create / edit meeting form

### Fields (schema-backed)

`meeting_type`, `status` (controlled by actions, not free dropdown of illegal transitions), `scheduled_at`, `scheduled_end_at`, `location_type`, `location_name`, `location_address`, `online_meeting_url`, `purpose`, `outcome`.

### Required by intent

| Intent | Required |
|--------|----------|
| **Draft** | `meeting_type`; `status=draft`; location optional; schedule optional |
| **Scheduled** | `meeting_type`; `scheduled_at`; `location_type`; end ≥ start if set |
| **Complete** | Meeting must be non-cancelled; recommend `status` was scheduled (UI); optional short `outcome` |
| **Cancel** | Non-cancelled meeting; optional short reason **not** stored unless product adds field later — use activity only in 4B.2 |

### Conditional location

| `location_type` | Rules |
|-----------------|-------|
| `online` | `online_meeting_url` optional but recommended; show only to users with manage (or view+manage); never in activity/list |
| `in_person` | `location_name` **required**; `location_address` optional |
| `telephone` | No address/URL required; `location_name` optional (e.g. “LA switchboard”) |
| `other` | `location_name` required |

### Validation / limits

- Date/time: parse with site timezone (`wp_timezone` semantics already in service).  
- End after start.  
- Past `scheduled_at`: **allowed** with warning (catch-up logging), not hard-block.  
- `purpose` / `outcome`: max 255 (schema); **non-clinical** — guidance text: no assessment narrative.  
- Status transitions: UI-enforced map (§6); reject illegal POST status.  
- Data minimisation: never put URL/email/phone into activity descriptions (already true in services).

---

## 6. Status-transition rules

### Approved UI transition map (strict)

```
draft      → scheduled   (schedule / save as scheduled)
draft      → cancelled
scheduled  → scheduled   (reschedule: datetime change via reschedule())
scheduled  → completed
scheduled  → cancelled
completed  → (none in UI)
cancelled  → (none in UI)
```

**Disallowed in UI:** completed→scheduled, cancelled→scheduled, completed→draft, cancelled→draft, draft→completed (skip schedule unless product later allows “log past meeting” as scheduled+complete two-step).

### Service vs UI gap (decision)

Phase 4B.1 `update()` / `reschedule()` / `complete()` are looser than this map. **4B.2 UI must enforce the map.** Recommend 4B.2.5 optionally harden services to match (without schema change).

### Activity / timestamps

| Event | When | Activity action | Stamps |
|-------|------|-----------------|--------|
| Create | `create()` | `meeting_created` | `created_at`/`updated_at` |
| Field edit without schedule change | `update()` | `meeting_updated` | `updated_at` |
| Datetime/status→scheduled via reschedule | `reschedule()` | `meeting_rescheduled` | `scheduled_at`, clear completed/cancelled |
| Complete | `complete()` | `meeting_completed` | `completed_at` set; `cancelled_at` null |
| Cancel | `cancel()` | `meeting_cancelled` | `cancelled_at` set |

**Reschedule definition:** Change to `scheduled_at` and/or `scheduled_end_at` on a non-cancelled meeting using `reschedule()` (forces scheduled). Cosmetic field edits without datetime → `meeting_updated`.

**Corrections without destroying audit:** Prefer new meeting or attendance-only updates; do not delete activity rows. No “correction event” type in 4B.1 — do not invent without approval.

**Pipeline:** No meeting status change may alter canonical workflow stage in 4B.2.

---

## 7. Internal attendee UI

### Requirements (aligned with services)

- Pick from `UserProvider::get_assignable_users()` (capability `VIEW_REFERRALS`).  
- Multiple internals; `meeting_role` free text (≤150); `attendance_status` controlled.  
- Duplicate `user_id` rejected by service.  
- Identity = `user_id` only (no free-text name as identity).  
- Deleted/unavailable users: show “Unavailable user #{id}” if `get_userdata` fails; allow remove; block re-add if not assignable.

### Placement recommendation

**After meeting exists:** attendee panel on **meeting detail/edit page** (not on create-first-step only). Pattern: save meeting → redirect to detail with attendee forms (care-team “add then edit” simplicity).

- Dynamic rows or small “Add internal attendee” POST form on detail page — **prefer separate POST actions on the meeting detail page**, not a wholly separate route for MVP (unless overcrowding).  
- Optional later: `/meetings/{id}/attendees/…` if forms grow.

### Lifecycle

| Meeting status | Add | Update attendance/role | Remove |
|----------------|-----|------------------------|--------|
| draft / scheduled | Yes | Yes | Yes |
| completed | No add; **attendance correction Yes** | Yes (attendance/role) | No (prefer absent/declined) |
| cancelled | No | No | No |
| archived referral | No | No | No |

**Access:** Adding an internal attendee must **not** change AccessPolicy visibility or `assigned_to`.

---

## 8. External participant UI

### Fields

| Field | Required | Notes |
|-------|----------|-------|
| `display_name` | **Yes** | |
| `professional_role` | Optional | |
| `organisation` | Optional | |
| `email` | Optional | Authorised view only |
| `telephone` | Optional | Authorised view only |
| `participant_category` | Recommended | Controlled |
| `meeting_role` | Optional | |
| `attendance_status` | Default `invited` | |

### Categories (Phase 4B.1 — already implemented)

`la_officer`, `social_worker`, `commissioner`, `client`, `family`, `advocate`, `jm_staff`, `other`.

Audit request listed `family_member` / `healthcare_professional` — **not in schema**. Use existing `family` and `other` (or `advocate`) for 4B.2; refinement = later controlled-value change (no schema change in this audit).

### Visibility / minimisation

- Email/telephone: only users with `can_view_referral_meeting_contacts` — Assessor view (if granted) sees name/role/org/category/status only. Archived referrals: authorised managers retain contact read; writes remain blocked via `can_manage_referral_meetings`.  
- Never in URLs, activity descriptions, Management Dashboard, or emails (emails deferred).  
- Correction: edit form; removal allowed on draft/scheduled; on completed prefer status `absent`/`declined` over delete.

---

## 9. Attendance workflow

| Rule | Recommendation |
|------|----------------|
| Default on add | `invited` |
| Who updates | Users with `can_manage_referral_meetings` + referral mutate scope (active referral) |
| Before completion | Yes — confirmed/declined useful pre-meeting |
| Completion requires attendance review? | **No hard block** — optional checklist warning if any still `invited` |
| All attendees final state? | Encouraged not required |
| Late additions | Allowed on draft/scheduled only |
| Removed attendees | Activity `meeting_attendee_removed`; row gone; no soft-delete table |

Practical: keep admin burden low; do not force MAR-style completeness for acquisition meetings.

---

## 10. Meeting-detail design

**Separate page** (print-friendly), with edit mode or linked edit page.

Show: type, status badge+text, date/time, location (URL only if authorised), purpose, outcome, internal list, external list (contacts per §8), attendance, created/updated/completed/cancelled metadata, authorised action buttons (Edit, Schedule/Reschedule, Complete, Cancel, Back).

Avoid modal-only workflows. Combination allowed: detail is read-focused; “Edit” opens form page or inline edit section on same page (prefer **same page sections** for attendees + top actions to reduce route count).

---

## 11. Route design

Follow `PortalRouter` nested clinical pattern. Proposed **additions** (not implemented):

| Route key | Pattern | Helpers (proposed) |
|-----------|---------|-------------------|
| `referral_meetings` | `/referrals/([0-9]+)/meetings/?$` | `PortalUrls::referral_meetings($id)` |
| `referral_meeting_new` | `/referrals/([0-9]+)/meetings/new/?$` | `referral_meeting_new($id)` |
| `referral_meeting` | `/referrals/([0-9]+)/meetings/([0-9]+)/?$` | `referral_meeting($id, $meeting_id)` |
| `referral_meeting_edit` | `/referrals/([0-9]+)/meetings/([0-9]+)/edit/?$` | `referral_meeting_edit($id, $meeting_id)` |

- `QV_ID` = referral id; `QV_ENTITY` = meeting id.  
- Add keys to `PortalNavigation::REFERRAL_RELATED_ROUTES`.  
- **Rewrite flush:** adding rules requires bumping `PortalRouter::REWRITE_VERSION` (product still 1.4.0; rewrite version bump is an implementation detail — approve explicitly; currently **1.2.2**).  
- **No public routes.**

Flash examples: `jmrs_meeting=created|updated|scheduled|rescheduled|completed|cancelled`, `jmrs_meeting_attendee=added|updated|removed`, errors via `jmrs_meeting_error=1` + transient or query message codes.

---

## 12. Controller design

Prefer a dedicated handler (mirror `CareTeamHandler`) dispatched from `PortalController` / small `MeetingsDispatcher`, **not** infinite POSTs inside `render_referral_view`.

### Responsibilities

| Concern | Behaviour |
|---------|-----------|
| GET list | `can_view_referral` + view-meetings rule (§13); load `list_by_referral` + counts |
| GET detail/edit/new | Same; verify `meeting.referral_id === referral_id` |
| POST create/update/reschedule/complete/cancel | Nonce + `can_manage_referral_meetings` + not archived + valid transition |
| POST attendee add/update/remove | Same + meeting belongs to referral |
| Redirects | Back to detail or list; success query args |
| 404 vs 403 | Prefer **404** for missing/inaccessible referral or meeting (IDOR-safe), matching existing portal |
| Archived | GET allowed if view; POST rejected (service already returns `archived`) |

Wire real `ReferralMeetingService` / `MeetingAttendeeService` only.

---

## 13. Capability matrix

Use `AccessPolicy::can_manage_referral_meetings()` (= express-interest roles, including archived-mutate gate) for **mutations**.
Use `AccessPolicy::can_view_referral_meeting_contacts()` for external email/telephone and online meeting URL (same roles, **without** requiring mutate).  
**View** requires `can_view_referral` first (meeting-management must not bypass visibility).

| Action | JM Admin | Referral Manager | Care Coordinator | Assessor | Support Worker | WP Admin |
|--------|----------|------------------|------------------|----------|----------------|----------|
| View meetings (visible referral) | Yes | Yes | Yes | **Yes*** | **No†** | Yes |
| View external email/phone | Yes | Yes | Yes | No | No | Yes |
| Create / edit draft / schedule / reschedule | Yes | Yes | Yes‡ | No | No | Yes |
| Manage attendees / attendance | Yes | Yes | Yes‡ | No | No | Yes |
| Complete / cancel | Yes | Yes | Yes‡ | No | No | Yes |
| View archived-referral meetings | Yes if can view archived referral | Same | Same | Yes if can view | No | Yes |
| Mutate when archived | No | No | No | No | No | No |

\* Assessor: view-only when `can_view_referral` — supports assessment context without commercial mutate.  
† Support Worker: **deny** meeting view for MVP (acquisition meetings; aligns with Phase 4B.0 audit). Revisit only with product approval.  
‡ Care Coordinator: only where `can_view_referral` / mutate already allow (unrestricted list today).

No new WP capability required for 4B.2 MVP if product accepts reusing meeting-management helper; optional later `jmrs_manage_referral_meetings` capability remains a **business decision**.

---

## 14. Security model

Every write must verify:

1. Authenticated portal user  
2. Capability / `can_manage_referral_meetings` (or view gate for GET)  
3. `can_view_referral` (scope — Support Worker constraint)  
4. Meeting `referral_id` matches URL referral id (IDOR)  
5. Nonce (`jmrs_meeting_*_{referral_id}` / `_{meeting_id}` pattern)  
6. UI transition map + controlled values  
7. Archived → no writes  

Also: sanitise/escape all output; online URL `esc_url` on display; never put PII in query strings; disable double-submit via Post-Redirect-Get; deleted users handled safely; status-tampering rejected server-side even if service is loose (prefer harden service in 4B.2.5).

---

## 15. Archived-referral behaviour

| Action | Behaviour |
|--------|-----------|
| View list/detail | Allowed if user can view archived referral |
| Create / edit / schedule / attendees / complete / cancel | **Disabled** |
| Exceptional mutate after archive | **None** in 4B.2 |

Matches existing service `archived` gate and clinical mutate patterns. Retention rules unchanged.

---

## 16. Activity Timeline presentation

Existing actions already logged. Timeline continues to show last N activities on referral view — no separate meeting activity feed required for MVP.

| Action | Suggested display label | Description content |
|--------|-------------------------|---------------------|
| `meeting_created` | Meeting created | Type label only (existing) |
| `meeting_updated` | Meeting updated | Fixed short text |
| `meeting_rescheduled` | Meeting rescheduled | Fixed short text |
| `meeting_completed` / `meeting_cancelled` | Meeting completed / cancelled | Fixed short text |
| `meeting_attendee_added` | Meeting attendee added | Kind label (Internal/External) only |
| `meeting_attendee_updated` / `_removed` | Attendee updated / removed | Fixed short text |

**Never expose:** email, telephone, online URL, long purpose/outcome.

Optional clearer labels (copy-only): “Internal attendee added” / “External participant added” by mapping kind — still no PII.

---

## 17. Notification recommendation

| Channel | 4B.2 |
|---------|------|
| External participant email | **Deferred** |
| Internal email on assign/schedule | **Deferred** |
| In-portal flash notices | **Required** |
| WP admin email / Slack | **Deferred** |

No automatic emails in Phase 4B.2.

---

## 18. Management Dashboard boundary

**Do not integrate in 4B.2.**

Future boss-HTML / management fields (later phase, initials only):

- Who J&M is meeting (external name/org summary — still no phone/email on board)  
- Participant roles / categories  
- Meeting date, time, location type/name  
- J&M (internal) attendee names  
- Meeting status  

Deep link to referral meetings list only after UI UAT passes.

---

## 19. Champion / Transition Lead boundary

Columns exist from 4B.1; **no UI in 4B.2**.

Likely 4B.3 placement (documentation only):

- **Champion** — referral acquisition / responsibility strip on referral view  
- **Transition Lead** — placement / transition-planning area  

Do **not** mix into meeting attendee lists (different semantics from attendance).

---

## 20. Responsive and accessibility design

- Reuse `jmrs-portal-panel`, section headers, buttons, notice partials.  
- Tables → card rows at `max-width: 768px` (existing portal.css pattern).  
- Labels on all inputs; validation summary + field errors; focus first error.  
- Status badges include text.  
- Confirm Complete/Cancel with accessible `confirm()` or inline confirm panel (not modal-only).  
- Print: detail page hides nav/quick actions via print CSS if needed.  
- Keyboard operable attendee add/remove.  
- No colour-only status.

---

## 21. Implementation phases

| Phase | Scope | Independently reviewable |
|-------|-------|---------------------------|
| **4B.2.1** | Rewrites + URL helpers + MeetingsHandler + read-only list/detail + referral summary section + nav highlight | Yes |
| **4B.2.2** | Create / edit / schedule / reschedule / complete / cancel forms + flash + **UI and service transition enforcement together** | Yes |
| **4B.2.3** | Internal attendee management UI | Yes |
| **4B.2.4** | External participant UI + contact visibility rules | Yes |
| **4B.2.5** | Archived polish, IDOR/regression, a11y/print — **not** first lifecycle harden | Yes |
| **4B.2.6** | Manual UAT + checkpoint commit | Yes |

Adjust only if handler packaging differs; do not merge dashboard or champion work.

---

## 22. UAT plan (future — not passed)

| # | Scenario | Expected |
|---|----------|----------|
| 1 | JM Admin / Manager / Coordinator manage | Allowed on visible referral |
| 2 | Assessor | View only; mutate denied |
| 3 | Support Worker | Denied view+mutate |
| 4 | Referral-scope | Cannot open meetings for invisible referral (404) |
| 5 | Meeting list / summary | Correct grouping; no external PII |
| 6 | Draft create | OK; stage unchanged |
| 7 | Schedule / reschedule | Status+activity; stage unchanged |
| 8 | Complete / cancel | Stamps + activity; row retained on cancel |
| 9 | Invalid transitions | Rejected |
| 10 | Internal attendees | Add/update/remove; duplicate rejected |
| 11 | External participants | Name required; contacts manage-only |
| 12 | Attendance statuses | Updates without blocking complete |
| 13 | Archived referral | Read-only |
| 14 | IDOR / wrong meeting id | 404 |
| 15 | Bad/missing nonce | Rejected |
| 16 | PII minimisation | Activity + list clean |
| 17 | No workflow stage change | Confirmed |
| 18 | No emails | Confirmed |
| 19 | Responsive + print | Usable |
| 20 | Regression | Referral workspace, assessment scheduling, package cost, care team, management dashboard unchanged |

Do not mark passed until executed.

---

## 23. Business decisions still required

**Status (Phase 4B.2.1):** Approved product decisions recorded below. Implementation of read-only UI proceeds under these rules.

| # | Decision | Approved choice |
|---|----------|-----------------|
| 1 | UI location | **OPTION B** — summary on referral view + nested list/detail pages |
| 2 | Access | Assessor **view-only** when referral visible; Support Worker **deny all**; managers/coordinators/admins per referral scope; `can_view_referral_meetings()` vs `can_view_referral_meeting_contacts()` vs `can_manage_referral_meetings()` |
| 3 | Lifecycle reopen | **No reopen** of completed/cancelled in normal UI |
| 4 | Past `scheduled_at` | Allow with warning in **future write UI** (not 4B.2.1) |
| 5 | Attendance vs complete | Soft warning only in **future write UI** (not 4B.2.1) |
| 6 | Rewrite version | Bump **1.2.2 → 1.2.3** with version-gated flush; product 1.4.0; DB 2.29.0 |
| 7 | Service hardening | **Ship with Phase 4B.2.2 write forms** (UI + service lifecycle together). Direct POSTs must not bypass lifecycle. 4B.2.5 remains polish/regression — **not** the first server-side FSM enforcement |
| 8 | External contacts / online URL | **Contact-view** via `can_view_referral_meeting_contacts` (same commercial roles as manage, **without** archived-mutate gate). Mutation remains `can_manage_referral_meetings`. Omitted entirely from markup when contact-view denied. |
| 9 | Categories | Use Phase 4B.1 controlled values; **no expansion** |
| 10 | Quick action | Label **Meetings** → list. No Add/Schedule button until 4B.2.2 |

### Service hardening correction

Previous audit text that deferred server-side lifecycle enforcement to 4B.2.5 is **superseded**. When write forms are introduced in **Phase 4B.2.2**, service-level lifecycle validation must be hardened **at the same time** so crafted POST requests cannot rewind completed/cancelled meetings. Phase 4B.2.5 remains security polish and regression review only.

---

## 24. Risks

| Risk | Mitigation |
|------|------------|
| Referral view overcrowding | Summary-only on view; CRUD on nested pages |
| Confusion with assessment scheduling | Separate section; copy: “Referral meetings (not formal assessment)” |
| Service allows illegal transitions | UI enforce now; service harden in 4B.2.5 |
| PII leakage | List/activity/dashboard rules; manage-only contacts |
| IDOR on meeting ids | Always verify `meeting.referral_id` |
| Rewrite flush missed | Version bump + flush on deploy |
| Assessor/Support expectations | Explicit matrix in UAT |
| Timestamp vs activity (~1h) | Documented 4B.1 non-blocker; use `mysql2date` consistently |

---

## 25. Blockers

| Item | Status |
|------|--------|
| Phase 4B.1 foundation | Cleared (`b930611`) |
| Schema | Sufficient for UI — **no blocker** |
| Product decisions in §23 | **Awaiting approval** (soft blocker for implementation start) |
| Hard technical blocker | **None** |

---

## 26. Final verdict

**PHASE 4B.2 UI DESIGN READY FOR APPROVAL**

Implement only after explicit approval of OPTION B, the capability matrix, and the strict transition map.  

Do not begin Phase 4B.2 implementation in this audit task.  
Do not commit, push, tag, package, or deploy from this audit.
