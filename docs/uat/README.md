# UAT Package Index

User Acceptance Testing materials for **JM Healthcare Referral Platform** / JM Referral System.

| Document | Purpose |
| --- | --- |
| [UAT_PHASE_3.md](UAT_PHASE_3.md) | **Phase 3 final UAT** (acquisition pipeline → reporting, upgrade, regression) |
| [UAT_SUPPORTED_LIVING_V1_2.md](UAT_SUPPORTED_LIVING_V1_2.md) | **Master Supported Living UAT for v1.2.0** (homes → visits → reporting) |
| [UAT_SUPPORTED_LIVING_REPORTING.md](UAT_SUPPORTED_LIVING_REPORTING.md) | Reporting-focused Pass/Fail checklist (Phase 2G) |
| [UAT_PLAN.md](UAT_PLAN.md) | Portal package plan (originally v1.1.0) |
| [UAT_ROLE_MATRIX.md](UAT_ROLE_MATRIX.md) | Test personas and allowed/forbidden actions |
| [UAT_DATA_SETUP.md](UAT_DATA_SETUP.md) | Fictional seed data only |
| [UAT_SCENARIOS.md](UAT_SCENARIOS.md) | Portal end-to-end scenarios |
| [UAT_TEST_CASES.md](UAT_TEST_CASES.md) | Portal structured test cases |
| [UAT_DEFECT_LOG.md](UAT_DEFECT_LOG.md) | Defect register template |
| [UAT_SIGN_OFF.md](UAT_SIGN_OFF.md) | Formal sign-off form |

## Evidence

Store screenshots, exports, email captures, logs, and backups under a **private** folder such as:

```text
uat-evidence/
  screenshots/
  exports/
  email-tests/
  logs/
  backups/
```

`uat-evidence/` is gitignored. Do not commit real patient data or production secrets.

## Rule

**v1.3.0** production release requires completed Phase 3 UAT (`UAT_PHASE_3.md`) and the gate in `docs/RELEASE_CHECKLIST.md`, unless JM Project Owner formally documents an exception.
