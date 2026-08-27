# UAT — Phase 4B.2.4 External Participant Management

**Product:** 1.4.0  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** **1.2.6** (from 1.2.5)  
**Baseline checkpoint:** `ed59fd24dd4fd3554aec6a5224f658d81e9e766c` (Phase 4B.2.3)

**Scope:** Authorised add / edit / remove of **external** meeting participants only.  
**Out of scope:** Invitation emails, Phase 4B.2.5 polish, Management Dashboard, schema changes, product version bump.

**Categories (controlled):** `la_officer`, `social_worker`, `commissioner`, `client`, `family`, `advocate`, `jm_staff`, `other`.

**Duplicate decision:** Same-name / same-organisation external participants are **allowed**. No uniqueness rule.

**Manual UAT date:** **2026-08-27**  
**Overall classification:** **PARTIAL MANUAL UAT ACCEPTED**

Batches 1–3 fully successful. Batch 4 (cancelled/archived/scheduled-removal scenarios) **not** manually exercised; code-reviewed. Residual risk classified as **NON-BLOCKING DOCUMENTED UAT RISK**.

Do not treat this as a complete unrestricted PASS.

---

## Environment

| Item | Value |
| --- | --- |
| Staging | Manual UAT |
| Date | 2026-08-27 |
| Rewrite flush | 1.2.6 observed |
| Plugin activation | **PASS** (Batch 1) |

---

## Synthetic staging data (truthful)

| Item | Value |
| --- | --- |
| Referral | **7** (retained) |
| Meeting | **18** |
| External participant IDs | **13**, **14**, **16**, **18** (additional temporary validation participants may have existed) |
| Combined warning | Temporary internal attendee used; warning count **5** |
| Cleanup prefix | `Phase 4B.2.4%` |

**Cleanup:** Synthetic Phase 4B.2.4 attendees and meetings removed. Referral 7 retained. Truthful Activity Timeline records may remain. Workflow and responsibility fields unchanged. Do not invent uncaptured IDs.

---

## BATCH 1 — PASS

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| 1.01 | Plugin activation smoke test | **PASS** | 2026-08-27 |
| 1.02 | No activation fatal | **PASS** | Staging |
| 1.03 | Rewrite routes working | **PASS** | 1.2.6 |
| 1.04 | Scheduled meeting creation | **PASS** | Staging |
| 1.05 | Complete external participant creation | **PASS** | Staging |
| 1.06 | Manager email visibility | **PASS** | Staging |
| 1.07 | Manager telephone visibility | **PASS** | Staging |
| 1.08 | One addition activity | **PASS** | Staging |
| 1.09 | No contact PII in activity | **PASS** | Staging |
| 1.10 | External form contained no internal-user controls | **PASS** | Staging |
| 1.11 | External participant editing | **PASS** | Staging |
| 1.12 | Attendee kind remained external | **PASS** | Staging |
| 1.13 | user_id remained null | **PASS** | Staging |
| 1.14 | No-op edit created no activity | **PASS** | Staging |
| 1.15 | Minimum-fields participant creation | **PASS** | Staging |
| 1.16 | Invited default | **PASS** | Staging |
| 1.17 | Empty optional fields | **PASS** | Staging |
| 1.18 | External participant detail rendering | **PASS** | Staging |
| 1.19 | Internal attendee workflow unchanged | **PASS** | Staging |
| 1.20 | Meeting list and summary contained no contacts | **PASS** | Staging |
| 1.21 | No emails | **PASS** | Staging |
| 1.22 | No meeting-status change | **PASS** | Staging |
| 1.23 | No workflow/responsibility changes | **PASS** | Staging |

---

## BATCH 2 — PASS

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| 2.01 | Assessor safe fields visible | **PASS** | Staging |
| 2.02 | Assessor email absent | **PASS** | Staging |
| 2.03 | Assessor telephone absent | **PASS** | Staging |
| 2.04 | Contact PII absent from Assessor HTML source | **PASS** | Staging |
| 2.05 | Assessor mutation controls hidden | **PASS** | Staging |
| 2.06 | Missing name validation | **PASS** | Staging |
| 2.07 | Missing category validation | **PASS** | Staging |
| 2.08 | Missing meeting-role validation | **PASS** | Staging |
| 2.09 | Invalid category rejected | **PASS** | Staging |
| 2.10 | Invalid attendance rejected | **PASS** | Staging |
| 2.11 | Failed validation created no row or activity | **PASS** | Staging |
| 2.12 | Sticky values preserved | **PASS** | Staging |
| 2.13 | Invalid email rejected | **PASS** | Staging |
| 2.14 | Valid email accepted | **PASS** | Staging |
| 2.15 | Malicious telephone rejected | **PASS** | Staging |
| 2.16 | International telephone accepted | **PASS** | Staging |
| 2.17 | attendee_kind tampering blocked | **PASS** | Staging |
| 2.18 | user_id injection blocked | **PASS** | Staging |
| 2.19 | meeting_id tampering blocked | **PASS** | Staging |
| 2.20 | referral_id tampering blocked | **PASS** | Staging |
| 2.21 | Lifecycle timestamp tampering blocked | **PASS** | Staging |
| 2.22 | Tampering created no misleading activity | **PASS** | Staging |
| 2.23 | Internal attendee through external route denied | **PASS** | Staging |
| 2.24 | Same-name external participant allowed | **PASS** | Staging |
| 2.25 | No emails | **PASS** | Staging |
| 2.26 | Meeting status unchanged | **PASS** | Staging |
| 2.27 | Workflow/responsibility fields unchanged | **PASS** | Staging |

---

## BATCH 3 — PASS

| # | Test | Result | Evidence |
| --- | --- | --- | --- |
| 3.01 | Combined attendance warning displayed | **PASS** | Meeting 18 |
| 3.02 | Warning count was 5 | **PASS** | Staging |
| 3.03 | Internal and external attendees both counted | **PASS** | Staging |
| 3.04 | Warning contained no contact PII | **PASS** | Staging |
| 3.05 | Rendering warning created no activity | **PASS** | Staging |
| 3.06 | Completion remained allowed | **PASS** | Staging |
| 3.07 | Attendance was not automatically changed | **PASS** | Staging |
| 3.08 | Meeting completion succeeded | **PASS** | Staging |
| 3.09 | Completed Add action hidden | **PASS** | Staging |
| 3.10 | Completed Remove actions hidden | **PASS** | Staging |
| 3.11 | Completed identity/contact editing hidden | **PASS** | Staging |
| 3.12 | Correct Attendance actions visible | **PASS** | Staging |
| 3.13 | Participant 13 final correction succeeded | **PASS** | Staging |
| 3.14 | Only final statuses were available | **PASS** | Staging |
| 3.15 | Identity/contact remained read-only | **PASS** | Staging |
| 3.16 | No-op correction created no activity | **PASS** | Staging |
| 3.17 | Participant 14 correction succeeded | **PASS** | Staging |
| 3.18 | Participant 16 correction succeeded | **PASS** | Staging |
| 3.19 | Already-final no-op created no activity | **PASS** | Staging |
| 3.20 | Completed direct Add denied | **PASS** | Staging |
| 3.21 | Completed direct Remove denied | **PASS** | Staging |
| 3.22 | Denied attempts created no activity | **PASS** | Staging |
| 3.23 | Assessor completed safe-field view | **PASS** | Staging |
| 3.24 | Assessor completed HTML-source PII check | **PASS** | Staging |
| 3.25 | No emails | **PASS** | Staging |
| 3.26 | Workflow/responsibility fields unchanged | **PASS** | Staging |

---

## BATCH 4 — NOT RUN — CODE REVIEWED

Reason: Further repetitive manual testing stopped after three fully successful batches. Equivalent shared lifecycle and archived-referral enforcement were manually exercised in Phase 4B.2.3 for internal attendees.

| # | Test | Result | Code-review notes |
| --- | --- | --- | --- |
| 4.01 | Scheduled participant removal | **NOT RUN — CODE REVIEWED** | Handler `handle_external_attendee_remove` + service `remove_external_attendee` + `allows_external_attendee_remove` (draft/scheduled) |
| 4.02 | Cancelled-meeting external participant behaviour | **NOT RUN — CODE REVIEWED** | Lifecycle denies add/edit/remove/correction for cancelled; UI hides actions |
| 4.03 | Cancelled direct Add/Edit/Remove denial | **NOT RUN — CODE REVIEWED** | Handler 403 / service `invalid_transition`; non-leaking pairing via kind gate |
| 4.04 | Cancelled Assessor contact privacy | **NOT RUN — CODE REVIEWED** | Contacts only if `can_view_referral_meeting_contacts`; Assessor denied |
| 4.05 | Archived-referral external participant behaviour | **NOT RUN — CODE REVIEWED** | `require_manage_referral` / `resolve_meeting_context` archived → deny; detail `can_manage` false |
| 4.06 | Archived direct Add/Edit/Remove denial | **NOT RUN — CODE REVIEWED** | Dual enforcement handler + service |
| 4.07 | Archived Assessor contact privacy | **NOT RUN — CODE REVIEWED** | Assessor never has contact capability |
| 4.08 | Support Worker lifecycle regression | **NOT RUN — CODE REVIEWED** | `can_view_referral_meetings` denies Support Worker |
| 4.09 | Referral restoration regression | **NOT RUN — CODE REVIEWED** | No archive/restore behaviour changed this phase |

**Residual risk:** Cancelled and archived external-participant scenarios were verified by code review but were **not** manually exercised during Phase 4B.2.4.

**Classification:** **NON-BLOCKING DOCUMENTED UAT RISK**

---

## Code-review enforcement map (Batch 4)

| Rule | Handler | Service / policy |
| --- | --- | --- |
| Scheduled remove allowed | `handle_external_attendee_remove` + `allows_external_attendee_remove` | `remove_external_attendee` |
| Cancelled mutations denied | Lifecycle checks on new/edit/remove; `external_attendee_action_urls` omits actions | `allows_external_*` false for cancelled; correction only when completed |
| Archived mutations denied | `require_manage_referral` / `is_archived` | `resolve_meeting_context` → `archived` |
| Manager archived contacts | Detail uses `can_view_referral_meeting_contacts` (no mutate gate) | Read service omits contacts unless allowed |
| Assessor contact omission | Contacts not passed into present rows | `present_attendee_row` keys omitted |
| Support Worker denial | Meeting view gate | `can_view_referral_meetings` |
| Ownership / kind / nonce | `require_manage_external_attendee`, `verify_external_attendee_nonce` | `resolve_external_attendee_context` kind + meeting match |
| No activity on deny/validation | Redirect/404 before service success path | Activity only after successful persist |

No defects found. Behaviour not altered for this checkpoint.

---

## Sign-off

| Role | Name | Date | Verdict |
| --- | --- | --- | --- |
| Tester | Staging UAT | 2026-08-27 | Batches 1–3 **PASS**; Batch 4 code-reviewed |
| Reviewer | | 2026-08-27 | **PARTIAL MANUAL UAT ACCEPTED** |

**Overall:** **PARTIAL MANUAL UAT ACCEPTED** (non-blocking residual UAT risk documented)
