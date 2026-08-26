# UAT — Phase 4B.1 Data Foundation

Meetings, attendees, and responsibility columns (database / repository / internal services only).

**Product:** 1.4.0 (unchanged during development)  
**Database:** **2.29.0** (from 2.28.0)  
**Portal rewrite:** 1.2.2 (unchanged)

**Status:** Manual data-foundation UAT **passed** on **2026-08-26** (development/staging subdomain).

**Design source:** `docs/audits/PHASE_4B_MEETINGS_RESPONSIBILITIES_AUDIT.md`

**UI note:** No user-facing meeting UI, portal routes, or dashboard widgets ship in Phase 4B.1. Temporary development UAT harness was removed before checkpoint commit.

---

## Scope

In scope:

- Migration `2.28.0` → `2.29.0`
- Tables `jmrs_referral_meetings`, `jmrs_referral_meeting_attendees`
- Columns `champion_user_id`, `transition_lead_user_id` on referrals
- Domain constants / validation
- Repositories and internal services
- Activity logging from service operations
- Archive preservation + permanent-delete cascade (code path; cascade UAT optional)
- Uninstall opt-in table drops

Out of scope:

- Portal / Management Dashboard UI
- Workflow stage changes / VisualStageMap
- Notifications / external emails
- Fabricated legacy data / meeting backfill
- Product release

---

## AccessPolicy (final)

| Item | Finding |
|------|---------|
| Helpers retained | `can_manage_referral_meetings()` (meeting-management); `can_assign_referral_responsibilities()` (responsibility-assignment) |
| Existing methods changed | None — both alias `can_express_interest()` |
| Role capability matrix | Unchanged |
| Assessor / Support Worker | Denied meeting-management and responsibility-assignment |
| Visibility | Helpers do not grant referral visibility; future UI must still enforce referral-level scope |

---

## Timestamp / timezone audit (non-blocker)

Observed during UAT: a meeting row timestamp and the Activity Timeline display can appear ~1 hour apart on UK summer time.

| Layer | Convention |
|-------|------------|
| Meeting `created_at` / `updated_at` / complete / cancel | `current_time('mysql')` — WordPress **site-local** wall clock (existing JMRS pattern) |
| Meeting `scheduled_at` normalize | `DateTimeImmutable(..., wp_timezone())` then format — **site-local**, no GMT conversion |
| Upcoming queries | Compare against `current_time('mysql')` (site-local) |
| Activity `created_at` | Insert omits explicit time; MySQL `DEFAULT CURRENT_TIMESTAMP` (server clock — often UTC) |
| Activity Timeline UI | `mysql2date( date + time format, created_at )` — same as rest of JMRS; treats stored DATETIME as site-local display string |

**Conclusion:** No accidental double timezone conversion in Phase 4B.1 meeting code. Scheduled values use intended WordPress/site timezone semantics. The ~1h gap vs Activity Timeline matches the pre-existing distinction between domain rows written with `current_time('mysql')` and activity rows stamped by MySQL default. **Non-blocker** for Phase 4B.1 checkpoint; do not broaden datetime changes here.

---

## Result table

Tester: development UAT operator · Date: **2026-08-26** · Environment: development/staging subdomain

| Test | Expected result | Actual result | Status | Referral number |
|------|-----------------|---------------|--------|-----------------|
| AccessPolicy review | Additive helpers only; no existing permission change | Confirmed | **PASS** | — |
| DB migration to 2.29.0 | Option matches Migrator | Confirmed | **PASS** | — |
| Meetings table + indexes | Prefixed table exists with expected indexes | Confirmed | **PASS** | — |
| Meeting-attendees table + indexes | Prefixed table exists with expected indexes | Confirmed | **PASS** | — |
| `champion_user_id` column | Nullable column present | Confirmed | **PASS** | — |
| `transition_lead_user_id` column | Nullable column present | Confirmed | **PASS** | — |
| `assigned_to` preserved | Ownership column unchanged by migration | Confirmed | **PASS** | — |
| Migration-created meetings | 0 | 0 | **PASS** | — |
| Migration-created attendees | 0 | 0 | **PASS** | — |
| Migration-created responsibility assignments | 0 | 0 | **PASS** | — |
| Draft meeting creation | Row created; stage unchanged | Confirmed | **PASS** | Test referral |
| Meeting update | Purpose/location/schedule fields update | Confirmed | **PASS** | Test referral |
| Schedule / reschedule | Status scheduled; activity; stage unchanged | Confirmed | **PASS** | Test referral |
| Meeting completion | Completed; `completed_at` set; `cancelled_at` null | Confirmed | **PASS** | Test referral |
| Meeting cancellation | Cancelled; row retained; activity | Confirmed | **PASS** | Test referral |
| Multiple meetings / list-by-referral | Both retained; list correct | Confirmed | **PASS** | Test referral |
| Upcoming / latest queries | Documented behaviour | Confirmed | **PASS** | Test referral |
| Invalid meeting type | Rejected; no row | Confirmed | **PASS** | Test referral |
| Invalid meeting status | Rejected; no row | Confirmed | **PASS** | Test referral |
| Invalid location type | Rejected; no row | Confirmed | **PASS** | Test referral |
| Invalid attendee kind | Rejected; no row | Confirmed | **PASS** | Test referral |
| Invalid attendance status | Rejected; no row | Confirmed | **PASS** | Test referral |
| Internal attendee add/update/list/remove | Works via services | Confirmed | **PASS** | Test referral |
| Duplicate internal attendee | Rejected | Confirmed | **PASS** | Test referral |
| Missing internal `user_id` | Rejected | Confirmed | **PASS** | Test referral |
| External attendee add/update/list/remove | Synthetic PII only | Confirmed | **PASS** | Test referral |
| Missing external name | Rejected | Confirmed | **PASS** | Test referral |
| Activity-log PII minimisation | No email/phone/URL in descriptions | Confirmed | **PASS** | Test referral |
| Champion assign / clear / restore | Works; `assigned_to` unchanged | Confirmed | **PASS** | Test referral |
| Champion reassignment (second assignable user) | Reassign if available | Not exercised with a second assignable user | **NOT RUN** | Test referral |
| Transition lead assign / clear / restore | Works; owner/stage unchanged | Confirmed | **PASS** | Test referral |
| Transition lead reassignment (second user) | Reassign if available | Not exercised with a second assignable user | **NOT RUN** | Test referral |
| `assigned_to` unchanged through responsibility tests | Unchanged | Confirmed | **PASS** | Test referral |
| Workflow stage unchanged by meetings | Unchanged | Confirmed | **PASS** | Test referral |
| No emails sent | None from meeting/responsibility services | Confirmed | **PASS** | Test referral |
| Activity-log events | Expected Phase 4B actions recorded | Confirmed | **PASS** | Test referral |
| Archive preservation | Meetings/attendees retained | Confirmed | **PASS** | Test referral |
| Restore preservation | Meetings/attendees still attached | Confirmed | **PASS** | Test referral |
| Permanent-delete cascade | Attendees then meetings then referral | Not performed | **NOT RUN** | — |
| Migration idempotency | dbDelta; no INSERT/backfill; no email/activity | Code inspection | **CODE REVIEWED** | — |
| Staff Dashboard regression | Loads | Confirmed | **PASS** | — |
| Management Dashboard regression | Loads | Confirmed | **PASS** | — |
| Referrals / assessments / Package Costs / homes-occupancies | Load | Confirmed | **PASS** | — |
| No new normal navigation item | None | Confirmed | **PASS** | — |
| No public meeting route | None | Confirmed | **PASS** | — |
| Timestamp vs Activity Timeline (~1h) | Expected site-local vs activity DB default | Documented non-blocker | **PASS** (non-blocker) | — |
| Harness removed before commit | No temporary harness in tree | Required for checkpoint | **PASS** | — |

---

## Design invariants (confirmed)

- Product remains **1.4.0**; DB is **2.29.0**; portal rewrite **1.2.2**
- Meetings are separate from assessment scheduling (`jmrs_referral_assessments`)
- `assigned_to` remains referral owner
- Champion and transition lead do **not** automatically grant AccessPolicy visibility
- No canonical workflow / VisualStageMap change
- No legacy meeting or responsibility backfill

---

## Rollback notes

JMRS does not ship down migrations. Rollback = redeploy prior code + leave empty `2.29.0` tables/columns in place (or DBA drop unused tables after backup). Do not invent destructive migrator reverse steps.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Developer | Development UAT | 2026-08-26 | PASS (data foundation) |
| Reviewer | | | |
