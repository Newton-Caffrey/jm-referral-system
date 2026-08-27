# UAT — Phase 4B.2.3 Internal Attendee Management

**Product:** 1.4.0  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** **1.2.5** (from 1.2.4)  
**Baseline checkpoint:** `04919505a9c11832741bb91e99728e2b3d0838ef` (Phase 4B.2.2)

**Scope:** Authorised add / edit / remove of **internal** meeting attendees only.  
**Out of scope:** External participant management (Phase 4B.2.4), emails, schema changes, product version bump, Management Dashboard, assessment scheduling, champion/transition UI.

**Eligibility rule (final):** Internal staff selector uses `UserProvider::get_assignable_users()` / `is_assignable()` — WordPress users with capability `jmrs_view_referrals` (`VIEW_REFERRALS`). Does not include every WordPress account. Support Worker accounts that hold `VIEW_REFERRALS` may be recorded as attendees; attendee membership grants **no** referral access, meeting access, mutation permission, or external-contact visibility. Deleted/unavailable stored users render as “Unavailable user” (code-reviewed).

**Overall result:** **PASS**  
**Manual staging UAT completed:** **2026-08-27**

**Next phase:** Phase 4B.2.4 will introduce external participant management. Not started.

---

## Environment

| Item | Value |
| --- | --- |
| Staging URL | Staging (manual UAT) |
| Tester | Staging UAT |
| Date | 2026-08-27 |
| Rewrite flush observed | 1.2.5 once — **PASS** |

---

## Synthetic staging data (truthful)

**Referral:** ID **7** (retained after cleanup)

**Known meetings:**

| Meeting ID | Role in UAT |
| --- | --- |
| 15 | Scheduled then completed (attendance-warning testing) |
| 16 | Draft attendee edit/removal testing |
| 17 | Scheduled attendee removal testing |

Additional cancelled/role-test meetings may have been created; exact IDs were **not all captured**. Do not invent missing IDs.

**Known attendee IDs:**

| Attendee ID | Notes |
| --- | --- |
| 7 | Administrator attendee on meeting 15 |
| 8 | Support Worker attendee on meeting 15 |
| NOT CAPTURED | Draft attendee on meeting 16 |
| 10 | Scheduled-removal attendee |
| 12 | Cancelled-meeting attendee |

**Cleanup:** Synthetic rows matched purpose prefix `Phase 4B.2.3%`.

| Cleanup check | Result |
| --- | --- |
| All synthetic Phase 4B.2.3 attendees removed | Confirmed |
| All synthetic Phase 4B.2.3 meetings removed | Confirmed |
| Referral 7 retained | Confirmed |
| Workflow stage / `assigned_to` / champion / transition lead unchanged | Confirmed |

Truthful staging activity-log entries may remain on the fictional referral. Referral was **not** permanently deleted.

---

## ACTIVATION / DEPENDENCY WIRING

**Defect discovered and resolved before functional testing.**

During the first staging deployment, plugin activation failed because:

1. `MeetingAttendeeService` was instantiated in `registerReferralControllers()`
2. The service was **not** passed into `registerStaffPortal()`
3. `MeetingsHandler` constructor argument #6 therefore received `null`

**Correction:**

- Pass the existing `MeetingAttendeeService` instance from `registerReferralControllers()` to `registerStaffPortal()`
- Add a required non-null `MeetingAttendeeService` parameter to `registerStaffPortal()`
- Use that instance in the sole `MeetingsHandler` construction (no second service instance)

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| W1 | Activation fatal reproduced | **PASS** | Staging 2026-08-27 |
| W2 | Root cause identified | **PASS** | Missing DI into `registerStaffPortal()` |
| W3 | Dependency wiring corrected | **PASS** | `Plugin.php` |
| W4 | Corrected plugin activation | **PASS** | Staging after fix |
| W5 | Staff Portal opened after correction | **PASS** | Staging |
| W6 | No second service instance created | **PASS** | Composition root |
| W7 | Versions remained unchanged | **PASS** | 1.4.0 / 2.29.0 / 1.2.5 |

---

## ROUTES / REWRITES

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| R1 | Rewrite version 1.2.5 refresh | **PASS** | Staging UAT |
| R2 | Internal attendee Add route | **PASS** | Staging UAT |
| R3 | Internal attendee Edit route | **PASS** | Staging UAT |
| R4 | Internal attendee Remove route | **PASS** | Staging UAT |
| R5 | Existing meeting list/detail routes | **PASS** | Staging UAT |
| R6 | No public attendee route | **PASS** | Staging UAT |

---

## ACCESS / ROLE TESTS

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| A1 | JM Administrator attendee workflow | **PASS** | Staging UAT |
| A2 | Referral Manager attendee workflow | **PASS** | Staging UAT |
| A3 | Care Coordinator attendee workflow | **PASS** | Staging UAT |
| A4 | Assessor remained read-only | **PASS** | Staging UAT |
| A5 | Support Worker meeting access remained denied | **PASS** | Staging UAT |
| A6 | Support Worker attendee membership granted no access | **PASS** | Staging UAT |
| A7 | Normal referral scope remained enforced | **PASS** | Staging UAT |
| A8 | Attendee membership grants no referral/meeting access | **PASS** | Staging UAT |

---

## ADD INTERNAL ATTENDEE

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| B1 | Scheduled test meeting creation | **PASS** | Staging UAT |
| B2 | Administrator attendee added | **PASS** | Staging UAT |
| B3 | Default invited status | **PASS** | Staging UAT |
| B4 | Custom confirmed status | **PASS** | Staging UAT |
| B5 | Concise meeting role saved | **PASS** | Staging UAT |
| B6 | One `meeting_attendee_added` event | **PASS** | Staging UAT |
| B7 | Existing attendee excluded or disabled in selector | **PASS** | Staging UAT |
| B8 | Normal UI created no duplicate | **PASS** | Staging UAT |
| B9 | Support Worker could be recorded as an attendee | **PASS** | Staging UAT |
| B10 | Attendee membership granted no referral or meeting access | **PASS** | Staging UAT |
| B11 | No external-participant controls displayed | **PASS** | Staging UAT |
| B12 | No email or notification | **PASS** | Staging UAT |
| B13 | Meeting status unchanged | **PASS** | Staging UAT |
| B14 | Workflow and responsibility fields unchanged | **PASS** | Staging UAT |

---

## EDIT INTERNAL ATTENDEE

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| C1 | Staff identity displayed read-only | **PASS** | Staging UAT |
| C2 | `user_id` not editable | **PASS** | Staging UAT |
| C3 | `attendee_kind` not editable | **PASS** | Staging UAT |
| C4 | Meeting role updated on scheduled meeting | **PASS** | Staging UAT |
| C5 | Attendance status updated on scheduled meeting | **PASS** | Staging UAT |
| C6 | Draft role and attendance update | **PASS** | Staging UAT |
| C7 | Identity remained unchanged | **PASS** | Staging UAT |
| C8 | No-op edit created no activity | **PASS** | Staging UAT |
| C9 | Invalid attendance status rejected | **PASS** | Staging UAT |
| C10 | Invalid attendance created no activity | **PASS** | Staging UAT |

---

## DUPLICATE / ELIGIBILITY

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| D1 | Duplicate internal user rejected by service | **PASS** | Staging UAT |
| D2 | Duplicate created no second row | **PASS** | Staging UAT |
| D3 | Duplicate created no activity | **PASS** | Staging UAT |
| D4 | Invalid user ID rejected | **PASS** | Staging UAT |
| D5 | Non-eligible user validation via invalid-user test | **PASS** | Staging UAT |
| D6 | No email sent for failed attempts | **PASS** | Staging UAT |

**Residual concurrency risk (NON-BLOCKING DOCUMENTED RISK):** The database index on internal attendees is **not** unique. Service-layer `has_internal_user()` prevents normal duplicates; an extremely narrow concurrent-request race remains possible until a future schema decision. No migration or unique index in this phase.

---

## MASS ASSIGNMENT

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| M1 | Caller-controlled `user_id` blocked | **PASS** | Staging UAT |
| M2 | Caller-controlled `attendee_kind` blocked | **PASS** | Staging UAT |
| M3 | Caller-controlled `meeting_id` blocked | **PASS** | Staging UAT |
| M4 | Caller-controlled `referral_id` blocked | **PASS** | Staging UAT |
| M5 | External identity fields unavailable through internal UI | **PASS** | Staging UAT |
| M6 | Tampering created no misleading activity | **PASS** | Staging UAT |
| M7 | Internal identity remained immutable | **PASS** | Staging UAT |

---

## NONCE / SECURITY

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| S1 | Invalid nonce rejected | **PASS** | Staging UAT |
| S2 | Invalid nonce created no attendee | **PASS** | Staging UAT |
| S3 | Invalid nonce created no activity | **PASS** | Staging UAT |
| S4 | Wrong meeting/attendee pairing denied | **PASS** | Staging UAT |
| S5 | Reverse meeting/attendee pairing denied | **PASS** | Staging UAT |
| S6 | Pairing attempts created no activity | **PASS** | Staging UAT |
| S7 | External attendee cannot be edited through internal route | **NOT RUN — CODE REVIEWED** | Service returns not_found for non-internal kind |
| S8 | Permission denials were non-leaking | **PASS** | Staging UAT |

---

## REMOVE INTERNAL ATTENDEE

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| N1 | Draft attendee removal | **PASS** | Staging UAT |
| N2 | Scheduled attendee removal | **PASS** | Staging UAT |
| N3 | Confirmation page displayed staff and meeting context | **PASS** | Staging UAT |
| N4 | Confirmation stated WordPress user would not be deleted | **PASS** | Staging UAT |
| N5 | Attendee row removed | **PASS** | Staging UAT |
| N6 | WordPress staff account retained | **PASS** | Staging UAT |
| N7 | One `meeting_attendee_removed` activity retained | **PASS** | Staging UAT |
| N8 | Refresh created no duplicate removal event | **PASS** | Staging UAT |
| N9 | Old attendee Edit and Remove URLs denied after deletion | **PASS** | Staging UAT |
| N10 | Completed attendee removal denied | **PASS** | Staging UAT |
| N11 | Cancelled attendee removal denied | **PASS** | Staging UAT |
| N12 | Archived attendee removal denied | **PASS** | Staging UAT |

---

## COMPLETION ATTENDANCE WARNING

Previous Phase 4B.2.2 status: **NOT RUN — CODE REVIEWED**. Exercised manually in Phase 4B.2.3.

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| G1 | Scheduled meeting had two non-final attendees | **PASS** | Meeting 15 |
| G2 | One attendee was invited | **PASS** | Staging UAT |
| G3 | One attendee was confirmed | **PASS** | Staging UAT |
| G4 | Complete Meeting page displayed warning | **PASS** | Staging UAT |
| G5 | Warning count was 2 | **PASS** | Staging UAT |
| G6 | Warning exposed no staff emails or raw IDs | **PASS** | Staging UAT |
| G7 | Merely displaying warning created no activity | **PASS** | Staging UAT |
| G8 | Warning did not block completion | **PASS** | Staging UAT |
| G9 | Attendance values were not changed automatically | **PASS** | Staging UAT |
| G10 | Meeting completed successfully | **PASS** | Staging UAT |
| G11 | One `meeting_completed` event | **PASS** | Staging UAT |
| G12 | Meeting status became completed | **PASS** | Staging UAT |
| G13 | No email | **PASS** | Staging UAT |

---

## COMPLETED MEETING ATTENDEES

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| E1 | Add Internal Attendee hidden | **PASS** | Staging UAT |
| E2 | Remove actions hidden | **PASS** | Staging UAT |
| E3 | General meeting-role edit hidden | **PASS** | Staging UAT |
| E4 | Correct Attendance actions visible | **PASS** | Staging UAT |
| E5 | Staff identity read-only | **PASS** | Staging UAT |
| E6 | Meeting role read-only | **PASS** | Staging UAT |
| E7 | Final attendance choices only | **PASS** | Staging UAT |
| E8 | Attended correction succeeded | **PASS** | Staging UAT |
| E9 | Absent correction succeeded | **PASS** | Staging UAT |
| E10 | Invited unavailable as correction | **PASS** | Staging UAT |
| E11 | Confirmed unavailable as correction | **PASS** | Staging UAT |
| E12 | No-op final correction created no activity | **PASS** | Staging UAT |
| E13 | Completed direct Add route denied | **PASS** | Staging UAT |
| E14 | Completed direct Remove route denied | **PASS** | Staging UAT |
| E15 | Denied attempts created no activity | **PASS** | Staging UAT |
| E16 | Attendee membership still granted no access | **PASS** | Staging UAT |

---

## CANCELLED MEETING

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| F1 | Attendee remained visible read-only | **PASS** | Staging UAT |
| F2 | Add action hidden | **PASS** | Staging UAT |
| F3 | Edit action hidden | **PASS** | Staging UAT |
| F4 | Correct Attendance action hidden | **PASS** | Staging UAT |
| F5 | Remove action hidden | **PASS** | Staging UAT |
| F6 | Direct Add route denied | **PASS** | Staging UAT |
| F7 | Direct Edit route denied | **PASS** | Staging UAT |
| F8 | Direct Remove route denied | **PASS** | Staging UAT |
| F9 | Attempts created no attendee activity | **PASS** | Staging UAT |
| F10 | Attendee status was not changed automatically | **PASS** | Staging UAT |

---

## ARCHIVED REFERRAL

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| AR1 | Attendee remained visible read-only | **PASS** | Staging UAT |
| AR2 | Attendee mutation controls hidden | **PASS** | Staging UAT |
| AR3 | Direct Add route denied | **PASS** | Staging UAT |
| AR4 | Direct Edit route denied | **PASS** | Staging UAT |
| AR5 | Direct Remove route denied | **PASS** | Staging UAT |
| AR6 | Attempts created no activity | **PASS** | Staging UAT |
| AR7 | Meeting remained unchanged | **PASS** | Staging UAT |
| AR8 | Attendee remained unchanged | **PASS** | Staging UAT |
| AR9 | Referral restored successfully | **PASS** | Staging UAT |
| AR10 | Controls returned after restore | **PASS** | Staging UAT |
| AR11 | Post-restore attendee removal succeeded | **PASS** | Staging UAT |
| AR12 | Workflow stage unchanged | **PASS** | Staging UAT |
| AR13 | `assigned_to` unchanged | **PASS** | Staging UAT |
| AR14 | `champion_user_id` unchanged | **PASS** | Staging UAT |
| AR15 | `transition_lead_user_id` unchanged | **PASS** | Staging UAT |

---

## DELETED / UNAVAILABLE USER

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| I1 | Unavailable user fallback | **NOT RUN — CODE REVIEWED** | Deleting/disabling a real WordPress staff account solely for UAT was not considered appropriate. Stored-user fallback and batched display-name handling reviewed in code (`ReferralMeetingReadService` → “Unavailable user”). Do not fabricate a manual PASS. |

---

## REGRESSION

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| X1 | Meeting create workflow | **PASS** | Staging UAT |
| X2 | Meeting edit workflow | **PASS** | Staging UAT |
| X3 | Meeting schedule/reschedule workflow | **PASS** | Staging UAT |
| X4 | Meeting complete workflow | **PASS** | Staging UAT |
| X5 | Meeting cancel workflow | **PASS** | Staging UAT |
| X6 | Meeting list/detail routes | **PASS** | Staging UAT |
| X7 | External participants remained read-only | **PASS** | Staging UAT |
| X8 | Assessor safe-field view remained intact | **PASS** | Staging UAT |
| X9 | Support Worker denial remained intact | **PASS** | Staging UAT |
| X10 | Assessment scheduling unchanged | **PASS** | Staging UAT |
| X11 | Management Dashboard unchanged | **PASS** | Staging UAT |
| X12 | Activity Timeline loaded | **PASS** | Staging UAT |
| X13 | No external PII in activity descriptions | **PASS** | Staging UAT |
| X14 | No emails | **PASS** | Staging UAT |
| X15 | No workflow-stage changes | **PASS** | Staging UAT |
| X16 | Responsive attendee presentation | **PASS** | Staging UAT |
| X17 | Refresh did not repeat actions | **PASS** | Staging UAT |

---

## Sign-off

| Role | Name | Date | Verdict |
| --- | --- | --- | --- |
| Tester | Staging UAT | 2026-08-27 | **PASS** |
| Reviewer | | 2026-08-27 | **PASS** |

**Overall:** **PASS**
