# UAT — Phase 4J.0.1 Release-Candidate Preparation (1.5.0)

**Product:** 1.5.0 (bumped from 1.4.0)  
**Database:** 2.29.0 (unchanged — no new migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `53a9fb243fdbbfdcf83d72158ded42563b28131f` (Phase 4I.1)

**Scope:** Release-candidate metadata and documentation preparation only. Version bump, compatibility headers, CHANGELOG / release notes / checklist / packaging / installation / known-limitations updates. No workflow, permission, query, schema, or feature changes.

**Out of scope:** Phase 4J.1 full regression; ZIP; Git tag; production deploy; Assessor visibility changes; Chart.js vendoring; uninstall runtime changes.

**Manual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

**Deployment clarity:**
- Release-candidate working copy uploaded to **staging** for manual UAT: **YES**
- Production deployment performed: **NO**

---

## Versions

| Check | Result |
| --- | --- |
| Product version displayed as 1.5.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |
| No additional migration ran | **PASS** |

Current release matrix confirmed: Product **1.5.0** · Database **2.29.0** · Portal rewrite **1.2.7** · WordPress minimum **6.0** · PHP minimum **8.0**.

---

## Activation and WordPress metadata

| Check | Result |
| --- | --- |
| Activation smoke test | **PASS** |
| WordPress Plugins screen displayed version 1.5.0 | **PASS** |
| Requires WordPress 6.0 displayed or declared | **PASS** |
| Requires PHP 8.0 displayed or declared | **PASS** |
| Composer PHP platform check passed | **PASS** |
| Plugin loaded without a Composer platform error | **PASS** |
| `vendor/autoload.php` remained present | **PASS** |

---

## Staging smoke test

| Check | Result |
| --- | --- |
| Staff Portal opened | **PASS** |
| Management Dashboard opened | **PASS** |
| Operations tab opened | **PASS** |
| Meetings opened | **PASS** |
| Homes and Capacity opened | **PASS** |
| No missing class error | **PASS** |
| No missing template error | **PASS** |
| No activation warning | **PASS** |

---

## Existing data preservation

### Referral 10

| Check | Result |
| --- | --- |
| Retained correctly | **PASS** |
| Pipeline remained `care_commenced` | **PASS** |
| Referral status remained `in_progress` | **PASS** |
| Package remained Sent | **PASS** |
| LA decision remained Approved | **PASS** |
| Care commencement remained recorded and read-only | **PASS** |
| Active occupancy remained retained | **PASS** |

### Referral 11

| Check | Result |
| --- | --- |
| Retained in its existing staging state | **PASS** |

### General

| Check | Result |
| --- | --- |
| Existing staging data remained unchanged | **PASS** |
| Dashboard counts remained unchanged | **PASS** |
| Page refresh created no activity | **PASS** |
| Page refresh created no stage-history entry | **PASS** |
| No duplicate care commencement | **PASS** |
| No package mutation | **PASS** |
| No LA-decision mutation | **PASS** |
| No occupancy mutation | **PASS** |
| No email sent | **PASS** |
| No workflow mutation | **PASS** |

---

## Access smoke test

Focused smoke only. Complete role matrix repeats in Phase **4J.1**.

### JM Administrator / Referral Manager

| Check | Result |
| --- | --- |
| Existing access remained unchanged | **PASS** |
| Staff Portal access remained available | **PASS** |
| Management Dashboard remained available | **PASS** |

### Assessor

| Check | Result |
| --- | --- |
| Existing referral visibility remained unchanged | **PASS** |
| Management Dashboard remained denied | **PASS** |
| No new capability granted | **PASS** |

### Support Worker

| Check | Result |
| --- | --- |
| Existing restrictions remained unchanged | **PASS** |
| Management Dashboard remained denied | **PASS** |
| Commercial workflows remained denied | **PASS** |
| Occupancy management remained denied | **PASS** |
| No new capability granted | **PASS** |

---

## Documentation review

| Check | Result |
| --- | --- |
| README version matrix | **PASS** |
| `readme.txt` review | **PASS** |
| CHANGELOG 1.5.0 entry | **PASS** |
| Release notes review | **PASS** |
| Release checklist review | **PASS** |
| Packaging guide review | **PASS** |
| Installation and upgrade guide review | **PASS** |
| Rollback documentation review | **PASS** |
| Known limitations review | **PASS** |
| Compliance-supporting wording | **PASS** |

Confirmed documentation states:

- Release status remains **not yet released / release candidate**
- Proposed tag remains **`v1.5.0`**
- Planned artifact remains **`jm-referral-system-1.5.0.zip`**
- Replacement without uninstall is documented
- Database backup before upgrade is documented
- Rollback recommends previous plugin files plus pre-upgrade database
- Phase **4J.1** remains mandatory
- No release gate is prematurely marked complete

---

## readme.txt review

| Check | Result |
| --- | --- |
| Plugin name present | **PASS** |
| Short description present | **PASS** |
| Requires at least 6.0 | **PASS** |
| Tested up to 6.0 | **PASS** |
| Requires PHP 8.0 | **PASS** |
| Stable tag 1.5.0 | **PASS** |
| Licence information present | **PASS** |
| Description present | **PASS** |
| Installation section present | **PASS** |
| Upgrade notice present | **PASS** |
| 1.5.0 changelog entry present | **PASS** |

**Note:** `Tested up to: 6.0` is conservative and remains a documented non-blocker until a newer WordPress version is deliberately tested.

---

## Historical record protection

| Check | Result |
| --- | --- |
| Historical v1.4.0 release notes unchanged | **PASS** |
| Historical v1.4.0 tag references unchanged | **PASS** |
| Previous UAT dates unchanged | **PASS** |
| Previous commit hashes unchanged | **PASS** |
| Historical DB-version references preserved | **PASS** |
| Code-reviewed-only paths remained code-reviewed-only | **PASS** |
| Previous UAT evidence was not rewritten to 1.5.0 | **PASS** |
| No global replacement of historical 1.4.0 references occurred | **PASS** |

---

## Release boundary review

| Check | Result |
| --- | --- |
| No schema change | **PASS** |
| No migration change | **PASS** |
| No route change | **PASS** |
| No capability change | **PASS** |
| No query change | **PASS** |
| No workflow change | **PASS** |
| No metric-definition change | **PASS** |
| No third-party dependency added | **PASS** |
| No Chart.js asset downloaded | **PASS** |
| No Google Fonts code change | **PASS** |
| No external CDN behaviour change | **PASS** |
| No tag created | **PASS** |
| No ZIP created | **PASS** |
| No production deployment | **PASS** |

Staging working-copy deployment for manual UAT: **completed**.

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Tester | Manual release-preparation UAT | 2026-08-27 | **PASS** |
| Reviewer | | | |

**Verdict:** **PASS** (2026-08-27)
