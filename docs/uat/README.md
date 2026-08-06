# UAT Package Index — v1.1.0

User Acceptance Testing materials for **JM Healthcare Referral Platform** / JM Referral System **v1.1.0**.

| Document | Purpose |
| --- | --- |
| [UAT_PLAN.md](UAT_PLAN.md) | Purpose, scope, entry/exit, severity, smoke checklist |
| [UAT_ROLE_MATRIX.md](UAT_ROLE_MATRIX.md) | Test personas and allowed/forbidden actions |
| [UAT_DATA_SETUP.md](UAT_DATA_SETUP.md) | Fictional seed data only |
| [UAT_SCENARIOS.md](UAT_SCENARIOS.md) | 10 end-to-end scenarios |
| [UAT_TEST_CASES.md](UAT_TEST_CASES.md) | 64 structured test cases |
| [UAT_DEFECT_LOG.md](UAT_DEFECT_LOG.md) | Defect register template |
| [UAT_SIGN_OFF.md](UAT_SIGN_OFF.md) | Formal sign-off |

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

v1.1.0 production release requires completed UAT and recorded sign-off (`UAT_SIGN_OFF.md`), unless JM Project Owner formally documents an exception.
