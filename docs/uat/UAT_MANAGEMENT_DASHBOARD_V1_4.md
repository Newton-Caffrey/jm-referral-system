# UAT — Management Dashboard (v1.4.0)

Manual acceptance record for the **J&M Healthcare Management Dashboard** feature release.

**Versions:** Product `1.4.0` · DB `2.28.0` · Portal rewrite `1.2.2`

Related: [RELEASE_NOTES_v1.4.0.md](../RELEASE_NOTES_v1.4.0.md) · [CHANGELOG.md](../../CHANGELOG.md) · [RELEASE_CHECKLIST.md](../RELEASE_CHECKLIST.md)

| Field | Value |
| --- | --- |
| Tester | Manual UAT (management / product) |
| Environment | Staging / UAT |
| Date | 2026-08-11 |
| Product version | `1.4.0` (expected) |
| DB version | `2.28.0` (expected) |
| Portal rewrite | `1.2.2` (expected) |

---

## Result summary

| Area | Result |
| --- | --- |
| Visual design | **Pass** |
| Management Dashboard route | **Pass** |
| Live KPI consistency | **Pass** |
| Here Now | **Pass** |
| All Who Reached | **Pass** |
| Stage-history verification | **Pass** |
| Proposed Package Value | **Pass** |
| Care commenced excluded from live PPV | **Pass** |
| Homes & Capacity | **Pass** |
| Ownership | **Pass** |
| Recommended Actions | **Pass** |
| Referral links / canonical stage | **Pass** |

Manual UAT for the above areas **passed** prior to release preparation. Do not treat this document as automated test evidence.

---

## Scope checks (release prep)

| Check | Expected | Status |
| --- | --- | --- |
| Existing operational Dashboard retained | Separate route; unchanged | Confirmed in prep audit |
| Visual stages presentation-only | Mapping per `VisualStageMap` | Confirmed in prep audit |
| No DB migration | `Migrator::DB_VERSION` = `2.28.0` | Confirmed |
| Rewrite bump only | `1.2.1` → `1.2.2` | Confirmed |
| No prototype SEED / Edit Data / localStorage | Absent from runtime | Confirmed in prep audit |
| Read-only surface | No management mutation endpoints | Confirmed in prep audit |

---

## Access smoke (manual)

| Role | Expect |
| --- | --- |
| JM Administrator | Allowed |
| Referral Manager | Allowed |
| Care Coordinator | Allowed where commercial policy permits |
| Assessor | Denied |
| Support Worker | Denied |

Confirm against live AccessPolicy / capabilities on the target environment before production promote.
