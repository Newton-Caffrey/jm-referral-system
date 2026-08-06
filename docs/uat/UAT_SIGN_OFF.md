# UAT Sign-Off — JM Referral Platform v1.1.0

Complete after exit criteria in `UAT_PLAN.md` are met.  
This document records organisational acceptance for release readiness. It does **not** constitute legal, clinical, or regulatory compliance certification.

---

## Release under test

| Field | Value |
| --- | --- |
| Product | JM Referral System |
| Version tested | 1.1.0 (or build ID: _________) |
| Database schema | _________ |
| Git commit / ZIP name | _________ |
| Environment | Staging URL: _________ |
| UAT start date | _________ |
| UAT end date | _________ |
| Portal base path | _________ |
| Public form page | _________ |

---

## Summary of testing

| Item | Result |
| --- | --- |
| Mandatory scenarios (1–10) | Pass / Fail / Partial — see `UAT_SCENARIOS.md` |
| Security & permission cases | Pass / Fail |
| Backup & restore drill | Pass / Fail |
| Smoke checklist | Pass / Fail |
| Open Critical defects | Count: ___ |
| Open High defects (core) | Count: ___ |

---

## Known accepted limitations

List product limitations accepted for go-live (from `docs/KNOWN_LIMITATIONS.md` and UAT):

1.  
2.  
3.  

Examples (edit as needed): portal reports page not yet available; operational alerts page remains wp-admin; wp-admin redirect left disabled; no CAPTCHA.

---

## Outstanding defects

| Defect ID | Severity | Summary | Disposition |
| --- | --- | --- | --- |
| | | | Fix post-release / Accept / Block |

---

## Production recommendation

Select one:

- [ ] **Approved for production**  
- [ ] **Approved with accepted limitations** (listed above)  
- [ ] **Not approved**

Conditions before production (if any):

-  
-  

---

## Signatures

### Developer

| Field | |
| --- | --- |
| Name | |
| Role | Developer / Technical lead |
| Date | |
| Signature | |
| Statement | I confirm the build under test matches the listed commit/ZIP, defects are accurately logged, and no known Critical issues remain unaddressed except as recorded above. |

### JM Project Owner

| Field | |
| --- | --- |
| Name | |
| Role | JM Project Owner |
| Date | |
| Signature | |
| Statement | I accept the UAT outcome and the production recommendation selected above for operational go-live planning. |

### JM Operational Representative

| Field | |
| --- | --- |
| Name | |
| Role | Operations / Care operations |
| Date | |
| Signature | |
| Statement | I confirm core operational workflows were exercised with fictional data and are acceptable for staff use subject to listed limitations. |

### JM Data Protection / Compliance Representative (where applicable)

| Field | |
| --- | --- |
| Name | |
| Role | Data protection / compliance (organisational) |
| Date | |
| Signature | |
| Statement | I have reviewed the UAT security/permission outcomes and evidence handling approach. This sign-off is organisational only and is **not** a legal determination of regulatory compliance. |

---

## Evidence location (private)

Evidence stored at (do not commit secrets):  

`______________________________________________`

Folders expected: `screenshots/`, `exports/`, `email-tests/`, `logs/`, `backups/`.
