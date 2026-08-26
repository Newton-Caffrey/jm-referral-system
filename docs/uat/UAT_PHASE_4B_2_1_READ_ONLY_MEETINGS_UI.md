# UAT — Phase 4B.2.1 Read-Only Meetings UI

Operational portal read-only meetings list, detail, and referral summary.

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (unchanged)  
**Portal rewrite:** **1.2.3** (from 1.2.2)

**Status:** Manual staging UAT **PASS** — completed **2026-08-26**.

**Design:** `docs/audits/PHASE_4B_2_MEETING_UI_WORKFLOW_AUDIT.md` (approved decisions for 4B.2.1 + 4B.2.1A contact-view correction)

**Out of scope this phase:** create/edit/complete/cancel UI, attendee mutations UI, champion/transition UI, Management Dashboard, emails, schema changes, Phase 4B.2.2.

**Phase 4B.2.2** will introduce write forms and server-side lifecycle enforcement. Not started.

---

## Staging fixtures used (removed after UAT)

Temporary Tools fixture page created synthetic records for read-only UI testing, then cleaned up. Fixture source code and `JMRS_ENABLE_PHASE_4B21_UAT_FIXTURES` gate were removed before the Phase 4B.2.1 checkpoint commit. Staging `wp-config` constant removed; Tools page no longer available.

**Historical IDs (removed after UAT — not present in committed runtime):**

| Entity | IDs |
|--------|-----|
| Referral (retained) | **7** |
| Meetings (removed) | **4**, **5**, **6**, **7** |
| Attendees (removed) | **3**, **4**, **5**, **6** |

Tracked fixture meetings/attendees and the tracking option were removed. Test referral **7** was retained. Truthful activity history on that referral was retained. Workflow stage, `assigned_to`, `champion_user_id`, and `transition_lead_user_id` were unchanged by cleanup.

Synthetic values used during UAT (e.g. `test-social-worker@example.invalid`, `0000000000`, `https://meet.example.invalid/jmrs-uat`) existed only as temporary fixture data and were deleted with the tracked rows. They are **not** in production runtime code.

**Not tested:** permanent deletion of a referral.

---

## Contact visibility (Phase 4B.2.1A)

| Helper | Purpose |
|--------|---------|
| `can_view_referral_meetings()` | List/detail/summary access |
| `can_view_referral_meeting_contacts()` | External email, telephone, online meeting URL (read) |
| `can_manage_referral_meetings()` | Mutation / write gate only (false on archived) |

Authorised managers may **view** contacts on archived referrals; they still **cannot** mutate. Assessor/Support Worker never get contacts. Attendee / champion / transition-lead assignment does not grant contact view.

---

## Routes

| Intent | Path (under staff-portal base) |
|--------|--------------------------------|
| List | `/referrals/{id}/meetings/` (`referral_meetings`) |
| Detail | `/referrals/{id}/meetings/{meeting_id}/` (`referral_meeting`) |

Rewrite flush: version-gated via `PortalRouter::REWRITE_VERSION = 1.2.3` only when stored option differs. GET-only; no POST handlers; no nonces required for this phase.

---

## Result table

**Overall:** PASS (2026-08-26, staging)

### Administrator / manager

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Meetings summary visible | Shown on referral workspace | Pass | PASS | 2026-08-26 | Staging UAT |
| Meetings quick action visible | Label “Meetings” | Pass | PASS | 2026-08-26 | Staging UAT |
| Total meeting count | Correct (fixture total 4) | Pass | PASS | 2026-08-26 | Staging UAT |
| Draft count | Correct (1) | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled count | Correct (1) | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed count | Correct (1) | Pass | PASS | 2026-08-26 | Staging UAT |
| Cancelled count | Correct (1) | Pass | PASS | 2026-08-26 | Staging UAT |
| Next scheduled meeting | Future scheduled fixture | Pass | PASS | 2026-08-26 | Staging UAT |
| Summary — no participant email | Omitted | Pass | PASS | 2026-08-26 | Staging UAT |
| Summary — no telephone | Omitted | Pass | PASS | 2026-08-26 | Staging UAT |
| Summary — no online-meeting URL | Omitted | Pass | PASS | 2026-08-26 | Staging UAT |
| Quick action opens list | Navigates to meetings list | Pass | PASS | 2026-08-26 | Staging UAT |

### Meeting list

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| All four fixtures displayed | Draft, scheduled, completed, cancelled | Pass | PASS | 2026-08-26 | Staging UAT |
| Status badges correct | Accurate per status | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled/upcoming ordering | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Internal attendee counts | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| External participant counts | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| No external email | Absent from list | Pass | PASS | 2026-08-26 | Staging UAT |
| No external telephone | Absent from list | Pass | PASS | 2026-08-26 | Staging UAT |
| No online-meeting URL | Absent from list | Pass | PASS | 2026-08-26 | Staging UAT |
| No Add button | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No Edit button | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No Complete button | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No Cancel button | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No attendee-management controls | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Administrator meeting details

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Draft detail | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled detail | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed detail | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Cancelled detail | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Internal attendees | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| External participants | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| Attendance statuses | Correct | Pass | PASS | 2026-08-26 | Staging UAT |
| External email visible | Authorised manager | Pass | PASS | 2026-08-26 | Staging UAT |
| External telephone visible | Authorised manager | Pass | PASS | 2026-08-26 | Staging UAT |
| Online-meeting URL visible | Authorised manager (scheduled online) | Pass | PASS | 2026-08-26 | Staging UAT |
| Safe online-link output | `rel="noopener noreferrer"` / `target="_blank"` | Pass | PASS | 2026-08-26 | Staging UAT |
| Back to Meetings | Works | Pass | PASS | 2026-08-26 | Staging UAT |
| Back to Referral | Works | Pass | PASS | 2026-08-26 | Staging UAT |
| No mutation controls | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Assessor

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Summary visible | Where referral access permits | Pass | PASS | 2026-08-26 | Staging UAT |
| Quick action visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting list accessible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting detail accessible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Participant name visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Professional role visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Organisation visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Category visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Attendance status visible | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| External email absent | Rendered page | Pass | PASS | 2026-08-26 | Staging UAT |
| External telephone absent | Rendered page | Pass | PASS | 2026-08-26 | Staging UAT |
| Online-meeting URL absent | Rendered page | Pass | PASS | 2026-08-26 | Staging UAT |
| Restricted values absent from HTML source | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No mutation controls | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Support Worker

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Summary hidden | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Quick action hidden | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Direct list URL denied | Non-leaking denial | Pass | PASS | 2026-08-26 | Staging UAT |
| Direct detail URL denied | Non-leaking denial | Pass | PASS | 2026-08-26 | Staging UAT |
| Denial does not reveal meeting info | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Security / IDOR

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Valid meeting + wrong referral denied | Non-leaking | Pass | PASS | 2026-08-26 | Staging UAT |
| Invalid meeting ID denied | Project-standard not-found | Pass | PASS | 2026-08-26 | Staging UAT |
| No participant info leaked | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No meeting ownership leaked | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Non-leaking response used | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Archived referral

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Manager — summary | Viewable | Pass | PASS | 2026-08-26 | Staging UAT |
| Manager — list | Viewable | Pass | PASS | 2026-08-26 | Staging UAT |
| Manager — detail | Viewable | Pass | PASS | 2026-08-26 | Staging UAT |
| Manager — external contacts | Permitted contact fields visible | Pass | PASS | 2026-08-26 | Staging UAT |
| Manager — online URL | Permitted URL visible | Pass | PASS | 2026-08-26 | Staging UAT |
| Mutation disabled | No write controls | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessor contact-restricted | Contacts/URL omitted | Pass | PASS | 2026-08-26 | Staging UAT |
| Support Worker denied | Remains denied | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived-state presentation | Correct read-only banner/state | Pass | PASS | 2026-08-26 | Staging UAT |
| Referral restored successfully | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Workflow stage restored/unchanged | Unchanged | Pass | PASS | 2026-08-26 | Staging UAT |
| Owner unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Champion unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Transition lead unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Activity / regression

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Viewing creates no activity | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Referral workspace regression | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessment scheduling regression | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Management Dashboard unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Existing portal routes | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| No emails sent | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No workflow-stage changes | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Responsive/mobile layout | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Print layout | Passed | Pass | PASS | 2026-08-26 | Staging UAT |

### Fixture cleanup

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Tracked meetings removed | IDs 4–7 gone | Pass | PASS | 2026-08-26 | Staging UAT |
| Tracked attendees removed | IDs 3–6 gone | Pass | PASS | 2026-08-26 | Staging UAT |
| Tracking option removed | `jmrs_phase_4b21_uat_fixtures` gone | Pass | PASS | 2026-08-26 | Staging UAT |
| Test referral retained | Referral 7 | Pass | PASS | 2026-08-26 | Staging UAT |
| Truthful activity history retained | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Workflow stage unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| assigned_to unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| champion_user_id unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| transition_lead_user_id unchanged | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Staging fixture constant removed | `wp-config` cleared | Pass | PASS | 2026-08-26 | Staging UAT |
| Temporary Tools page unavailable | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

---

## Query / performance (committed strategy)

- Status counts: grouped SQL for the referral
- Meeting list: paginated (`LIMIT`/`OFFSET`); total count for page controls (no silent truncation)
- Attendee counts: grouped by meeting IDs for the list page
- Detail attendees: loaded once per meeting
- Internal display names: batched via `UserProvider`
- List/summary omit external contact PII / online URL from presentation
- New repository queries use prepared SQL

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Developer | Staging UAT | 2026-08-26 | PASS |
| Reviewer | | 2026-08-26 | PASS (staging) |
