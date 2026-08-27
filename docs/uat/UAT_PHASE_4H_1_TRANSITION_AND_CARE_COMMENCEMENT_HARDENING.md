# UAT — Phase 4H.1 Transition Planning and Care Commencement Hardening

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `9e2a705300bf89c7161e3f88111f99abbb386150` (Phase 4G.1)

**Scope:** Harden existing derived Transition Planning panel, Place Resident entry, and record-once Care Commencement. Do **not** add target-home reservation, checklist, new stages, emails, or schema.

**Out of scope:** Proposed home/bedroom fields; reservation statuses; placement-confirmed stage; new meeting types; transition checklist; lifecycle → completed; occupancy auto-create on commence; Homes vacancy counting change.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Definitions

| Item | Rule |
| --- | --- |
| Transition readiness | Derived only — no transition-plan row |
| Transition Lead | `transition_lead_user_id` — grants no access |
| SL placement | Active occupancy via Place Resident |
| Place Resident | Creates occupancy + `placement_started`; **no** pipeline advance |
| Care commencement | Record-once → `care_commenced`; status stays/`new`→`in_progress` |
| Hard blockers | Prevent Confirm Care Commenced |
| Soft warnings | Informational; funding ack required when funding not yes |
| Capacity semantic | Homes: all active occupancy unavailable; Management: occupied now vs future move-ins — **KNOWN PRODUCT SEMANTIC — NOT CHANGED IN PHASE 4H.1** |
| Assessor panel | **EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION** |

---

## Versions / activation

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| No activation fatal | **PASS** |
| Product remained 1.4.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |

---

## Test referral

| Item | Value |
| --- | --- |
| Staging referral ID | **10** |
| Actual care setting | **supported_living** |
| Initial pipeline | `transition_planning` |
| Initial LA decision | approved |
| Initial package status | Sent |
| Final pipeline | `care_commenced` |
| Final referral status | `in_progress` |
| Final package status | Sent |
| Care commencement | Recorded |
| Occupancy | Active staging occupancy **retained** |

Occupancy ID, home ID, bedroom ID, package ID, decision ID, user IDs, move-in date, and care-commencement datetime were **not captured** (do not invent).

Referral **10** remains a staging **care_commenced** record with its active Supported Living occupancy retained for later staging work.

---

## Panel and GET

| Check | Result |
| --- | --- |
| Transition Planning panel opened | **PASS** |
| LA Approved prerequisite displayed correctly | **PASS** |
| Package remained Sent | **PASS** |
| Panel GET created no activity | **PASS** |
| Panel GET caused no pipeline change | **PASS** |
| Panel GET caused no referral-status change | **PASS** |
| Panel GET caused no email | **PASS** |

---

## Archive (before commencement)

| Check | Result |
| --- | --- |
| Referral archived before final commencement | **PASS** |
| Transition information remained readable to authorised users | **PASS** |
| Place Resident mutation unavailable or denied | **PASS** |
| Confirm Care Commenced hidden | **PASS** |
| Direct archived mutations denied | **PASS** |
| No occupancy created by denied request | **PASS** |
| No care commencement created | **PASS** |
| No activity created | **PASS** |
| No pipeline transition | **PASS** |
| No referral-status change | **PASS** |
| No email sent | **PASS** |
| Referral restored successfully | **PASS** |
| Valid controls returned after restoration | **PASS** |

---

## Access — Assessor

| Check | Result |
| --- | --- |
| Transition panel remained readable under existing product behaviour | **PASS** |
| Place Resident denied | **PASS** |
| Confirm Care Commenced denied | **PASS** |
| Responsibility management denied | **PASS** |
| Management Dashboard access denied | **PASS** |
| Assessor assignment granted no extra transition permission | **PASS** |

**EXISTING PRODUCT BEHAVIOUR — REQUIRES FUTURE JM CONFIRMATION:** Assessor visibility of transition and placement information remains an existing product behaviour requiring future JM confirmation.

---

## Access — Support Worker

| Check | Result |
| --- | --- |
| Transition-management access denied | **PASS** |
| Homes and occupancy management denied | **PASS** |
| Care-commencement access denied | **PASS** |
| Management Dashboard access denied | **PASS** |

---

## Access — responsibility membership

| Check | Result |
| --- | --- |
| Owner membership granted no additional placement capability | **PASS** |
| Champion membership granted no additional placement capability | **PASS** |
| Transition Lead membership granted no referral visibility or commencement capability | **PASS** |
| Meeting attendance granted no transition-management access | **PASS** |

---

## Invalid date and mass assignment

| Check | Result |
| --- | --- |
| Invalid date rejected | **PASS** |
| Future date rejected | **PASS** |
| Malformed date rejected | **PASS** |
| Markup/script date rejected safely | **PASS** |
| Array date rejected safely | **PASS** |
| Invalid attempts created no activity | **PASS** |
| Invalid attempts caused no pipeline transition | **PASS** |
| Invalid attempts caused no referral-status change | **PASS** |
| Raw malicious values were not rendered unsafely | **PASS** |
| Acquisition-pipeline tampering blocked | **PASS** |
| Referral-status tampering blocked | **PASS** |
| care_commenced_by tampering blocked | **PASS** |
| Care-setting tampering blocked | **PASS** |
| Occupancy-status tampering blocked | **PASS** |
| No arbitrary caller value persisted | **PASS** |
| No misleading activity created | **PASS** |

---

## Supported Living pathway

**MANUALLY TESTED — PASS**

| Check | Result |
| --- | --- |
| Care setting was supported_living | **PASS** |
| Active occupancy was required before care commencement | **PASS** |
| Home and bedroom ownership validated | **PASS** |
| Active home required | **PASS** |
| Active bedroom required | **PASS** |
| Bedroom belonged to selected home | **PASS** |
| Occupancy belonged to referral 10 | **PASS** |
| Commencement date was not before move-in | **PASS** |
| Commencement date was not in the future | **PASS** |

### Place Resident

| Check | Result |
| --- | --- |
| Place Resident succeeded | **PASS** |
| One active occupancy created | **PASS** |
| One placement_started activity created | **PASS** |
| Place Resident did not advance the pipeline | **PASS** |
| Place Resident did not confirm care commencement | **PASS** |
| Duplicate active occupancy for referral denied | **PASS** |
| Overlapping active bedroom occupancy denied | **PASS** |
| Duplicate placement activity not created | **PASS** |
| Occupancy association granted no new referral visibility | **PASS** |

Active staging occupancy **retained** (not deleted or ended via SQL).

---

## Own Home pathway

**NOT RUN — CODE REVIEWED**

Code review confirms:

- Own Home requires no Supported Living occupancy
- Active Supported Living occupancy creates a hard conflict
- Existing Own Home address behaviour remains a warning where applicable
- Valid commencement date must not be in the future
- Current approved hard requirements remain enforced
- Successful commencement uses the same record-once claim
- Pipeline advances to `care_commenced`
- Referral status remains `in_progress`
- No occupancy is automatically created

---

## Hard blockers and soft warnings

| Check | Result |
| --- | --- |
| Hard Blockers heading displayed clearly | **PASS** |
| Soft Warnings heading displayed clearly | **PASS** |
| Blocker wording was objective and actionable | **PASS** |
| Warning wording was objective and actionable | **PASS** |
| Soft warnings remained visible before commencement | **PASS** |
| Required acknowledgement was enforced | **PASS** |
| Submission without required acknowledgement was denied | **PASS** |
| Acknowledgement did not alter the underlying warning data | **PASS** |
| Acknowledgement created no separate activity event | **PASS** |
| No readiness percentage was displayed | **PASS** |
| No fake checklist state was created | **PASS** |

---

## Care commencement

| Check | Result |
| --- | --- |
| Confirm Care Commenced succeeded | **PASS** |
| Care commencement was claimed once | **PASS** |
| Exactly one care_commenced activity created | **PASS** |
| Pipeline advanced exactly once: transition_planning → care_commenced | **PASS** |
| Referral status remained in_progress | **PASS** |
| Referral was not automatically marked completed | **PASS** |
| Package remained Sent and unchanged | **PASS** |
| LA decision remained Approved and unchanged | **PASS** |
| Active occupancy remained linked and unchanged | **PASS** |
| No new email type was triggered | **PASS** |
| No target-home record created | **PASS** |
| No reservation record created | **PASS** |
| No placement-confirmed stage created | **PASS** |
| No transition checklist record created | **PASS** |

---

## Terminal and duplicate protection

| Check | Result |
| --- | --- |
| Care Commenced / read-only state displayed | **PASS** |
| Commencement date displayed safely | **PASS** |
| Recorded-by information displayed safely | **PASS** |
| Final pipeline displayed as care_commenced | **PASS** |
| Care setting displayed safely | **PASS** |
| Safe occupancy summary displayed | **PASS** |
| Confirm Care Commenced form hidden | **PASS** |
| Acknowledgement controls hidden | **PASS** |
| Editable commencement inputs absent | **PASS** |
| No editable hidden commencement form rendered | **PASS** |
| Direct second commencement denied | **PASS** |
| Browser refresh created no second commencement | **PASS** |
| Refresh created no duplicate care_commenced activity | **PASS** |
| Browser back/resubmit created no duplicate milestone | **PASS** |
| Original commencement data remained unchanged | **PASS** |
| Pipeline remained care_commenced | **PASS** |
| Referral status remained in_progress | **PASS** |
| Package remained Sent | **PASS** |
| LA decision remained Approved | **PASS** |
| No repeated stage-history entry | **PASS** |
| No activity created by the denied duplicate request | **PASS** |

---

## Capacity semantics

| Check | Result |
| --- | --- |
| Management Dashboard wording distinguished occupied-now from future move-ins | **PASS** |
| Homes operational pages continued treating future-dated active occupancy as unavailable for placement | **PASS** |
| No Homes vacancy semantics were changed | **PASS** |

**KNOWN PRODUCT SEMANTIC — NOT CHANGED IN PHASE 4H.1:** Future-dated active occupancy blocks placement availability, while the Management Dashboard distinguishes it from a resident physically occupying the bedroom today.

---

## Privacy

| Check | Result |
| --- | --- |
| Activity contained no clinical narrative | **PASS** |
| Activity contained no care-plan content | **PASS** |
| Activity contained no package total | **PASS** |
| Activity contained no LA notes or references | **PASS** |
| Activity contained no unnecessary full Own Home address | **PASS** |
| Activity contained no raw database IDs | **PASS** |
| No sensitive transition information in dashboard compact lists | **PASS** |

---

## Responsive (~375px)

| Check | Result |
| --- | --- |
| Transition panel remained inside the page container | **PASS** |
| Hard Blockers wrapped safely | **PASS** |
| Soft Warnings wrapped safely | **PASS** |
| Home and bedroom labels wrapped safely | **PASS** |
| Read-only state remained clear | **PASS** |
| No page-level horizontal overflow | **PASS** |
| Controls remained usable before commencement | **PASS** |

---

## Side-effect regression

| Check | Result |
| --- | --- |
| Package Cost record unchanged | **PASS** |
| Package remained Sent | **PASS** |
| LA decision unchanged | **PASS** |
| Assessment unchanged | **PASS** |
| Meetings unchanged | **PASS** |
| Meeting attendees unchanged | **PASS** |
| Owner / Champion / Transition Lead unchanged unless intentionally modified | **PASS** |
| Responsibilities unchanged | **PASS** |
| No unintended document changes | **PASS** |
| No new email | **PASS** |

---

## Sign-off

| Role | Result |
| --- | --- |
| Focused manual UAT | **PASS** 2026-08-27 |
| Supported Living path | **MANUALLY TESTED — PASS** |
| Own Home path | **NOT RUN — CODE REVIEWED** |
