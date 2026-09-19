# UAT — Phase 5A.2 Services, Terminology & Modules

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0**)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7  
**Baseline checkpoint:** `bfb9d265e7b4f44efde89fbc7f96794371d43d89` (Phase 5A.1)  
**Branch:** `develop/1.6.0`

**Scope:** Service catalogue (existing architecture), TerminologySettings, ModuleSettings, gating, focused manual UAT.

**Out of scope:** Schema/migrations; email intake; Graph/Gmail/OAuth; LA directory; licensing; main merge; tag; package; deploy.

**Manual UAT date:** **2026-09-19**  
**Overall result:** **PASS**

---

## SERVICES

| Check | Result | Notes |
| --- | --- | --- |
| Existing services preserved | **PASS** | Catalogue not overwritten |
| Create service | **PASS** | |
| Edit service | **PASS** | |
| Deactivate service | **PASS** | |
| Inactive not offered for new intake | **PASS** | |
| Historical referral shows old service | **PASS** | `service_type_id` + `service_required` snapshot |
| Ordering by name (no sort_order column) | **PASS** | Schema limitation documented |
| Invalid input rejected | **PASS** | |
| In-use delete blocked | **PASS** | |

---

## TERMINOLOGY

Temporarily changed labels (then restored defaults):

| Field | Test value |
| --- | --- |
| Referral / Referrals | Opportunity / Opportunities |
| Client / Clients | Person / People |
| Local Authority / Local Authorities | Council / Councils |
| Commissioner / Commissioners | Funding Contact / Funding Contacts |
| Service / Services | Support Service / Support Services |

| Check | Result | Notes |
| --- | --- | --- |
| Terminology saved | **PASS** | |
| Staff Portal labels | **PASS** | |
| Referral forms / list | **PASS** | |
| Referral view (generic labels) | **PASS** | |
| Public intake | **PASS** | |
| Services labels | **PASS** | |
| Dashboard headings in scope | **PASS** | |
| Reports / menu where in scope | **PASS** | |
| Stored referral / domain values unchanged | **PASS** | Display only |
| Defaults restored after test | **PASS** | |

---

## MODULES

| Check | Result | Notes |
| --- | --- | --- |
| Meetings disable/re-enable | **PASS** | |
| Assessments disable/re-enable | **PASS** | |
| Package Costing disable/re-enable | **PASS** | |
| LA Decisions disable/re-enable | **PASS** | |
| Supported Living disable/re-enable | **PASS** | |
| Transition disable/re-enable | **PASS** | |
| Management Dashboard disable/re-enable | **PASS** | |
| Reports disable/re-enable | **PASS** | |
| Navigation hides when disabled | **PASS** | |
| Direct mutation denied | **PASS** | |
| Historical records preserved | **PASS** | |
| Re-enable restores controls | **PASS** | |
| No data deletion | **PASS** | |
| No pipeline mutation on disable | **PASS** | |
| No historical activity change | **PASS** | |

---

## DEPENDENCIES

| Attempt | Expected | Result | Notes |
| --- | --- | --- | --- |
| Package Costing ON + Assessments OFF | Rejected | **PASS** | |
| LA Decisions ON + Package Costing OFF | Rejected | **PASS** | |
| Transition ON + LA Decisions OFF | Rejected | **PASS** | |
| Previous valid configuration preserved | Kept | **PASS** | Centralized in `ModuleSettings::update` |

---

## SUPPORTED LIVING

| Check | Result | Notes |
| --- | --- | --- |
| Homes navigation hidden while disabled | **PASS** | |
| Occupancy mutations denied | **PASS** | |
| Referral 10 occupancy retained | **PASS** | |
| Own Home workflow unaffected | **PASS** | |
| Re-enable restored controls | **PASS** | |
| Care-setting key `supported_living` unchanged | **PASS** | |
| No occupancy row deleted | **PASS** | |
| No automatic lifecycle transition | **PASS** | |

---

## MANAGEMENT DASHBOARD

| Check | Result | Notes |
| --- | --- | --- |
| Disabled module blocks omitted | **PASS** | |
| Enabled module metrics unchanged | **PASS** | |
| No broken links | **PASS** | |
| Dashboard GET non-mutating | **PASS** | |
| No misleading zero blocks for disabled modules | **PASS** | |

---

## ACCESS

| Persona | Result | Notes |
| --- | --- | --- |
| WP Administrator | **PASS** | |
| Platform Administrator | **PASS** | |
| Referral Manager denied | **PASS** | |
| Care Coordinator denied | **PASS** | |
| Assessor denied | **PASS** | |
| Support Worker denied | **PASS** | |

---

## SECURITY

| Check | Result | Notes |
| --- | --- | --- |
| Nonce failure | **PASS** | |
| Unknown module key | **PASS** | |
| Array input | **PASS** | |
| Markup terminology | **PASS** | |
| Oversized terminology | **PASS** | |
| Direct disabled-module POST | **PASS** | |
| No stored XSS | **PASS** | |

---

## RESPONSIVE

| Viewport | Result | Notes |
| --- | --- | --- |
| 1440px | **PASS** | |
| 1024px | **PASS** | |
| 768px | **PASS** | |
| 375px | **PASS** | |
| No horizontal overflow | **PASS** | |

---

## REGRESSION

| Check | Result | Notes |
| --- | --- | --- |
| Referral 10 unchanged | **PASS** | |
| Phase 5A.1 organisation branding unchanged | **PASS** | |
| Dashboard counts unchanged for enabled modules | **PASS** | |
| Default modules ON ⇒ workflow unchanged | **PASS** | |
| DB 2.29.0 | **PASS** | |
| Rewrite 1.2.7 | **PASS** | |
| No email intake | **PASS** | |

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Tester | Focused manual UAT | 2026-09-19 | **PASS** |
| Reviewer | | 2026-09-19 | **PASS** |
