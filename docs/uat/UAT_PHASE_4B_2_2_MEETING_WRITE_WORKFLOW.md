# UAT — Phase 4B.2.2 Meeting Write Workflow

Authorised meeting create/edit/schedule/complete/cancel with server-side lifecycle enforcement.

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (unchanged)  
**Portal rewrite:** **1.2.4** (from 1.2.3)

**Status:** Manual staging UAT **PASS** — completed **2026-08-26**.

**Out of scope:** attendee management UI (4B.2.3/4), emails, reopen, hard delete, schema changes, Management Dashboard, assessment scheduling, champion/transition UI, Phase 4B.2.3.

**Next phase:** Phase 4B.2.3 will introduce internal attendee management. Not started.

---

## Staging synthetic data (removed after UAT)

Fictional referral **7** was used for write-workflow UAT. Synthetic meetings created during staging:

| Meeting ID | Role in UAT |
|------------|-------------|
| **8** | Completed normal workflow |
| **9** | Cancelled from draft |
| **10** | Cancelled from scheduled |
| **11** | Retrospectively scheduled and completed |
| **14** | Scheduled location-switch / archive / access test |
| **15** | Mass-assignment tampering test |

Meeting IDs **12** and **13** were not successfully created; failed validation may have consumed auto-increment values.

**Cleanup (confirmed manually):** meetings 8, 9, 10, 11, 14 and 15 removed; attached attendee rows removed / none remaining; referral **7** retained; workflow stage, `assigned_to`, `champion_user_id`, and `transition_lead_user_id` unchanged.

These synthetic meeting records were **removed after UAT**. Truthful activity-log rows generated during staging UAT may remain on the fictional referral. The referral itself was **not** permanently deleted.

---

## Routes

| Intent | Route | Path |
|--------|-------|------|
| List | `referral_meetings` | `/referrals/{id}/meetings/` |
| Detail | `referral_meeting` | `/referrals/{id}/meetings/{meeting_id}/` |
| New | `referral_meeting_new` | `/referrals/{id}/meetings/new/` |
| Edit | `referral_meeting_edit` | `/referrals/{id}/meetings/{meeting_id}/edit/` |
| Schedule/Reschedule | `referral_meeting_schedule` | `/referrals/{id}/meetings/{meeting_id}/schedule/` |
| Complete | `referral_meeting_complete` | `/referrals/{id}/meetings/{meeting_id}/complete/` |
| Cancel | `referral_meeting_cancel` | `/referrals/{id}/meetings/{meeting_id}/cancel/` |

GET form / confirmation; POST with nonce; redirect-after-POST. Service and UI enforce the same lifecycle. Completed and cancelled meetings are terminal (no reopen, no hard delete). Past meeting dates are allowed with a non-blocking warning. Purpose/outcome are operational summaries, not clinical assessments. Attendees remain read-only in this phase.

---

## Lifecycle (service-enforced)

| From | Allowed |
|------|---------|
| new | → draft (`create_draft`); → scheduled (`create_scheduled`) |
| draft | update details; → scheduled; → cancelled |
| scheduled | update permitted non-time details; reschedule; → completed; → cancelled |
| completed | read-only terminal (no reopen) |
| cancelled | read-only terminal (no reopen) |

---

## Result table

**Overall:** PASS (2026-08-26, staging)

### Access and routes

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Rewrite / version-gated route refresh | 1.2.4 flush once | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting list route | Works | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting detail route | Works | Pass | PASS | 2026-08-26 | Staging UAT |
| Add meeting visible to authorised manager | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessor read-only meeting access | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessor write actions hidden | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessor direct write routes denied | Non-leaking | Pass | PASS | 2026-08-26 | Staging UAT |
| Support Worker meeting access denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Support Worker direct write routes denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Referral Manager workflow | Allowed per scope | Pass | PASS | 2026-08-26 | Staging UAT |
| Care Coordinator workflow | Allowed per scope | Pass | PASS | 2026-08-26 | Staging UAT |
| Normal referral scope enforced | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Create draft

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Draft creation | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting type validation | Controlled values | Pass | PASS | 2026-08-26 | Staging UAT |
| Purpose saved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Incomplete schedule allowed for draft | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Status forced to draft | Caller status ignored | Pass | PASS | 2026-08-26 | Staging UAT |
| One meeting_created activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No email | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No workflow-stage change | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Draft edit

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Draft edit | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| Permitted detail changes saved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Status remained draft | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No lifecycle timestamp created | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No-op edit created no activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Schedule

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Draft → scheduled | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| Required schedule fields | Enforced | Pass | PASS | 2026-08-26 | Staging UAT |
| Site-timezone display | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Online URL handling | Validated / required when online | Pass | PASS | 2026-08-26 | Staging UAT |
| Incompatible physical-address cleared | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Available actions updated | Edit / Reschedule / Complete / Cancel | Pass | PASS | 2026-08-26 | Staging UAT |
| One scheduling/rescheduling activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Scheduled edit

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Scheduled non-time details editable | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled datetime absent from general edit | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Status absent from edit form | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Lifecycle timestamps absent from edit form | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| referral_id absent from edit form | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled datetime unchanged by general edit | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No-op scheduled edit created no activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Reschedule

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Scheduled → scheduled reschedule | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| New datetime saved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| One meeting_rescheduled activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No duplicate activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Complete

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Scheduled → completed | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| completed_at populated | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Optional operational outcome saved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled data preserved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| One meeting_completed activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Refresh created no duplicate completion | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed meeting became terminal | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed edit route denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed schedule route denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed complete route denied | Idempotent / no duplicate | Pass | PASS | 2026-08-26 | Staging UAT |
| Completed cancel route denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No reopen path | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No delete path | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Cancel

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Draft → cancelled | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled → cancelled | Success | Pass | PASS | 2026-08-26 | Staging UAT |
| cancelled_at populated | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled details preserved after cancellation | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| One meeting_cancelled activity per cancellation | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Refresh created no duplicate cancellation | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Cancelled meeting became terminal | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Cancelled direct mutation routes denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No reopen path | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No hard-delete path | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Retrospective meeting

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Past-date warning displayed | Non-blocking | Pass | PASS | 2026-08-26 | Staging UAT |
| Warning did not block submission | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Retrospective meeting created as scheduled | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Past datetime preserved exactly | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Not presented as upcoming | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Retrospective completion | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Past scheduled datetime preserved after completion | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Validation

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| End-before-start rejected | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Missing online URL rejected | When scheduled online | Pass | PASS | 2026-08-26 | Staging UAT |
| Invalid online URL rejected | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Missing in-person location name rejected | When scheduled | Pass | PASS | 2026-08-26 | Staging UAT |
| Failed submissions created no meeting records | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Failed submissions created no activity records | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Sticky form values preserved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Location-type change cleared stale online URL | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Scheduled datetime unchanged during location edit | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Nonce and mass assignment

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Invalid nonce rejected | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Invalid nonce created no meeting | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Invalid nonce created no activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Caller-controlled status ignored | Forced draft/scheduled | Pass | PASS | 2026-08-26 | Staging UAT |
| Caller-controlled completed_at ignored | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Caller-controlled cancelled_at ignored | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Caller-controlled referral_id ignored | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Mass-assignment meeting remained draft | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Refresh created no duplicate meeting | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Refresh created no duplicate activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### IDOR and archive

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Wrong referral / valid meeting write pairing denied | Non-leaking | Pass | PASS | 2026-08-26 | Staging UAT |
| IDOR attempts created no activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived list/detail remained readable | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived write controls hidden | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived create denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived edit denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived reschedule denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived complete denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived cancel denied | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived attempts created no activity | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Archived manager contact visibility preserved | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Referral restored correctly | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| Meeting unchanged after archive/restore | Yes | Pass | PASS | 2026-08-26 | Staging UAT |

### Regression

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Meeting list/detail regression | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessor safe-field view | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Support Worker denial | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Assessment scheduling unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Management Dashboard unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Activity Timeline loaded | Yes | Pass | PASS | 2026-08-26 | Staging UAT |
| No external PII in activity descriptions | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| No emails sent | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Workflow stage unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| assigned_to unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| champion_user_id unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| transition_lead_user_id unchanged | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |
| Responsive forms | Passed | Pass | PASS | 2026-08-26 | Staging UAT |
| Browser refresh did not repeat actions | Confirmed | Pass | PASS | 2026-08-26 | Staging UAT |

### Attendance soft warning

| Test | Expected | Actual | Status | Date | Tester |
|------|----------|--------|--------|------|--------|
| Soft attendance warning before complete | Warning when invited/confirmed attendees exist; does not block | Code-reviewed only | **NOT RUN — CODE REVIEWED** | 2026-08-26 | Staging UAT |

**Reason:** Phase 4B.2.2 deliberately introduced no attendee-management interface and the manually created meetings had no attendees. The warning implementation was reviewed in code (`MeetingsHandler::has_incomplete_attendance`). A natural end-to-end test will be performed after attendee UI is introduced (Phase 4B.2.3+). Do not treat this as a fabricated PASS.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Developer | Staging UAT | 2026-08-26 | PASS |
| Reviewer | | 2026-08-26 | PASS (staging) |
