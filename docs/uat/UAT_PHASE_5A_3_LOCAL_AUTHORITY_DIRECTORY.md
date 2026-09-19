# UAT — Phase 5A.3 Local Authority Directory & Trusted Sender Foundation

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0**)  
**Database:** **2.30.0** (from **2.29.0**)  
**Portal rewrite:** 1.2.7  
**Baseline checkpoint:** `a956f65f065175733fe1750cdd2a21d090544c9d` (Phase 5A.2)  
**Branch:** `develop/1.6.0`

**Scope:** Local Authority directory tables, sender rules, matcher, admin CRUD, terminology labels, focused manual UAT.

**Out of scope:** Outlook/Graph/Gmail/OAuth/webhooks/mailbox sync/Referral Inbox; AI classification; EOI; licensing; referral/service schema changes; main merge; tag; package; deploy.

**Manual UAT date:** **2026-09-19**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.30.0** · Rewrite **1.2.7**

---

## MIGRATION

| Check | Result | Notes |
| --- | --- | --- |
| Plugin activation/load | **PASS** | No fatal |
| Upgrade from DB 2.29.0 | **PASS** | |
| DB becomes 2.30.0 | **PASS** | |
| Local Authority table created | **PASS** | `jmrs_local_authorities` |
| Sender Rule table created | **PASS** | `jmrs_local_authority_sender_rules` |
| Existing referrals preserved | **PASS** | |
| Existing services preserved | **PASS** | |
| Referral 10 unchanged | **PASS** | |
| Unique `(local_authority_id, rule_type, rule_value)` present | **PASS** | |

---

## AUTHORITY CRUD

| Check | Result | Notes |
| --- | --- | --- |
| Create authority | **PASS** | |
| Edit authority | **PASS** | |
| Deactivate | **PASS** | |
| Reactivate | **PASS** | |
| Invalid contact email rejected | **PASS** | |
| Unsafe website rejected (`javascript:`, non-http) | **PASS** | |
| Markup/script in name/notes rejected | **PASS** | |
| Notes stored safely as plain text | **PASS** | |

---

## SENDER RULES

| Check | Result | Notes |
| --- | --- | --- |
| Add exact email | **PASS** | |
| Add domain | **PASS** | |
| Uppercase normalises correctly | **PASS** | |
| Duplicate same-authority exact email rejected | **PASS** | |
| Duplicate same-authority domain rejected | **PASS** | |
| Exact-rule deactivate/reactivate | **PASS** | |
| Domain-rule deactivate/reactivate | **PASS** | |
| Invalid email rejected | **PASS** | |
| URL-as-domain rejected | **PASS** | |
| Wildcard domain rejected | **PASS** | |
| Email-as-domain rejected | **PASS** | |
| Domain/path value rejected | **PASS** | |

---

## MATCHING

Staging matcher checks (provider-neutral `matchSender`):

Configured around **Alpha Council** (`exact_email` + `domain` on `alpha-council.example.org`).

| Check | Expected | Result | Notes |
| --- | --- | --- | --- |
| `referrals@alpha-council.example.org` | MATCH Alpha Council / exact_email | **PASS** | Exact precedence |
| `someone@alpha-council.example.org` | MATCH Alpha Council / domain | **PASS** | |
| `someone@adult.alpha-council.example.org` | MATCH Alpha Council / domain | **PASS** | Label-boundary subdomain |
| `someone@alpha-council.example.org.attacker.com` | NO_MATCH | **PASS** | Attacker-suffix protected |
| `random@example.com` | NO_MATCH | **PASS** | |
| `not-an-email` | INVALID_SENDER | **PASS** | |
| Inactive authority | NO_MATCH | **PASS** | Reactivation restores matching |
| Inactive domain rule | NO_MATCH | **PASS** | Reactivation restores matching |
| Conflicting active domain rules | AMBIGUOUS | **PASS** | Did not pick A or B by ID/order |

**Confirmations:** exact-email precedence; label-boundary subdomain matching; attacker-suffix protection; no naive substring matching; ambiguity never resolved by first row / lowest ID / insertion order.

Authority deactivation does **not** delete sender rules. Rule deactivation does **not** delete configuration rows.

---

## AMBIGUITY

| Check | Result | Notes |
| --- | --- | --- |
| Cross-authority overlap warning | **PASS** | |
| Conflicting active domain rules → AMBIGUOUS | **PASS** | |
| Matcher did not silently choose Authority A | **PASS** | |
| Matcher did not silently choose Authority B | **PASS** | |
| Temporary conflicting rule removed/deactivated after test | **PASS** | |

---

## TERMINOLOGY

Temporarily changed:

| Field | Test value |
| --- | --- |
| Local Authority | Council |
| Local Authorities | Councils |

| Check | Result | Notes |
| --- | --- | --- |
| Directory heading | **PASS** | Councils |
| Add Council | **PASS** | |
| Edit Council | **PASS** | |
| Terminology restored | **PASS** | Defaults restored |

Internal table/class identifiers (`jmrs_local_authorities`, `LocalAuthority*`) were **not** renamed.

---

## ACCESS

| Check | Result | Notes |
| --- | --- | --- |
| WP Administrator allowed | **PASS** | |
| Platform Administrator allowed | **PASS** | |
| Referral Manager denied | **PASS** | |
| Care Coordinator denied | **PASS** | |
| Assessor denied | **PASS** | |
| Support Worker denied | **PASS** | |

Capability: `jmrs_manage_settings`.

---

## SECURITY

| Check | Result | Notes |
| --- | --- | --- |
| Nonce failure denied | **PASS** | |
| Forged authority ID denied | **PASS** | |
| Forged sender-rule ID denied | **PASS** | |
| Unknown rule_type rejected | **PASS** | |
| Array input rejected | **PASS** | |
| No stored XSS | **PASS** | |
| Cross-authority forged rule edit denied | **PASS** | |

**Confirmations:** repository SQL prepared; raw request arrays not persisted; mutations require capability + nonce; rule values normalised before duplicate checks; rules cannot reference nonexistent authorities.

---

## RESPONSIVE

| Viewport | Result | Notes |
| --- | --- | --- |
| 1440 | **PASS** | |
| 1024 | **PASS** | |
| 768 | **PASS** | |
| 375 | **PASS** | |
| No horizontal overflow | **PASS** | |

---

## REGRESSION

| Check | Result | Notes |
| --- | --- | --- |
| 5A.1 branding unchanged | **PASS** | |
| 5A.2 terminology/modules unchanged | **PASS** | |
| Existing referral workflow unchanged | **PASS** | |
| Dashboard unchanged | **PASS** | |
| Product remains 1.5.0 | **PASS** | |
| DB remains 2.30.0 | **PASS** | |
| Rewrite remains 1.2.7 | **PASS** | |
| No Outlook/Graph/Gmail connection | **PASS** | |
| No Connect Outlook / Gmail UI | **PASS** | |

---

## TEMPORARY UAT DATA (staging)

Staging-only configuration left in place after UAT (not auto-deleted):

| Record | Status |
| --- | --- |
| **Alpha Council** | Expected still present on staging (test authority + sender rules) |
| **Beta Council** | Expected still present on staging if used for overlap/AMBIGUOUS tests |

Do **not** destructively clean without explicit instruction. Safe to leave as staging-only directory fixtures.

---

## Sign-off

| Role | Name | Date | Signature |
| --- | --- | --- | --- |
| Tester | | 2026-09-19 | Focused manual UAT |
| JM Project Owner | | | |

**Verdict after UAT:** **PHASE 5A.3 PASS**
