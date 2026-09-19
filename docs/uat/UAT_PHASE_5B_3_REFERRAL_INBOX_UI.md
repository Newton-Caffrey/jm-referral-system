# UAT — Phase 5B.3 Referral Inbox UI

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0**)  
**Database:** **2.31.0** (no migration)  
**Portal rewrite:** **1.2.8**  
**Baseline checkpoint:** `6ff66b7f8f5f1429d6590af47889f1693bb2b227` (Phase 5B.2)  
**Branch:** `develop/1.6.0`

**Scope:** Staff Portal Referral Inbox list/detail, filters, search, pagination, Start Review / Ignore / Duplicate / error recovery. No mailbox connector. No Accept → referral.

**Out of scope:** Schema/migration; Graph/Gmail/OAuth/webhooks/sync/tokens; detection engine; referral creation; attachment files; dashboard metrics; main merge; tag; package; deploy.

**Manual UAT date:** **2026-09-19**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.31.0** · Rewrite **1.2.8**

**UAT method:** Temporary admin-only staging fixture loader was used for controlled Inbox fixtures, then **completely removed** before checkpoint (no live `jmrs-uat-5b3-fixtures` route remains).

### Cleanup (post-UAT)

| Item | Result |
| --- | --- |
| Remaining Phase 5B.3 fixture Inbox rows | **0** |
| Remaining Phase 5B.3 attachment fixtures | **0** |
| Referrals deleted | **No** |
| Referral 10 unchanged | **Yes** |
| Local Authority data deleted | **No** |
| Sender rules deleted | **No** |
| Organisation / terminology / module settings deleted | **No** |

---

## LIST / NAVIGATION

| Check | Result | Notes |
| --- | --- | --- |
| Referral Inbox nav visible | **PASS** | |
| Inbox list loads | **PASS** | |
| All tab | **PASS** | |
| New tab | **PASS** | |
| Needs Review tab | **PASS** | |
| Accepted tab | **PASS** | |
| Ignored tab | **PASS** | |
| Duplicates tab | **PASS** | |
| Errors tab | **PASS** | |
| Tab counts accurate | **PASS** | |
| Newest items first | **PASS** | |

---

## SEARCH

| Check | Result | Notes |
| --- | --- | --- |
| OMEGA search fixture found | **PASS** | |
| Unrelated rows excluded | **PASS** | |

---

## PAGINATION

| Check | Result | Notes |
| --- | --- | --- |
| >20 fixtures span pages | **PASS** | |
| Page 2 | **PASS** | |
| Page 1 return | **PASS** | |

---

## DETAIL

| Check | Result | Notes |
| --- | --- | --- |
| Detail opens | **PASS** | |
| Sender correct | **PASS** | |
| Subject correct | **PASS** | |
| Body preview plaintext | **PASS** | |
| No HTML/script execution | **PASS** | |
| Test Fixture source label | **PASS** | |
| Detection badge | **PASS** | |

---

## ATTACHMENTS

| Check | Result | Notes |
| --- | --- | --- |
| Metadata filename displayed | **PASS** | |
| MIME displayed | **PASS** | |
| Size displayed | **PASS** | |
| Metadata status displayed | **PASS** | |
| No download action | **PASS** | |
| No preview/open action | **PASS** | |

---

## GET SAFETY

| Check | Result | Notes |
| --- | --- | --- |
| Refresh did not change status | **PASS** | |
| reviewed_by unchanged | **PASS** | |
| reviewed_at unchanged | **PASS** | |
| GET caused no mutation | **PASS** | |

---

## START REVIEW

| Check | Result | Notes |
| --- | --- | --- |
| new → needs_review | **PASS** | |
| Reviewer recorded | **PASS** | |
| reviewed_at recorded | **PASS** | |
| First-review timestamp preserved after refresh | **PASS** | |

---

## IGNORE / STALE ACTION

| Check | Result | Notes |
| --- | --- | --- |
| needs_review → ignored | **PASS** | |
| ignored_by recorded | **PASS** | |
| ignored_at recorded | **PASS** | |
| Stale duplicate action rejected safely | **PASS** | |
| Friendly conflict notice | **PASS** | |
| No SQL/error dump | **PASS** | |
| Ignored state preserved | **PASS** | |

---

## DUPLICATE

| Check | Result | Notes |
| --- | --- | --- |
| Duplicate display | **PASS** | |
| Original link | **PASS** | |
| Duplicate state read-only | **PASS** | |
| Valid target | **PASS** | |
| Self target rejected | **PASS** | |
| Nonexistent target rejected | **PASS** | |

---

## ERROR RECOVERY

| Check | Result | Notes |
| --- | --- | --- |
| Safe error code displayed | **PASS** | |
| Safe error message displayed | **PASS** | |
| No exception/SQL output | **PASS** | |
| error → needs_review | **PASS** | |
| Reviewer recorded | **PASS** | |
| Active error metadata cleared | **PASS** | |

---

## TERMINAL STATES

| Check | Result | Notes |
| --- | --- | --- |
| Ignored read-only | **PASS** | |
| Duplicate read-only | **PASS** | |
| Accepted read-only | **PASS** | |
| Linked referral 10 displayed | **PASS** | |
| View Referral authorised link | **PASS** | |

---

## ACCEPTANCE SAFETY

| Check | Result | Notes |
| --- | --- | --- |
| No Accept Referral button exists | **PASS** | |
| Inbox UI created no referral | **PASS** | |

---

## ACCESS CONTROL

| Check | Result | Notes |
| --- | --- | --- |
| Platform Administrator allowed | **PASS** | |
| Referral Manager allowed | **PASS** | |
| Care Coordinator allowed | **PASS** | |
| Assessor denied | **PASS** | |
| Support Worker denied | **PASS** | |
| Unauthorised direct list URL denied | **PASS** | |
| Unauthorised direct detail URL denied | **PASS** | |

---

## SECURITY

| Check | Result | Notes |
| --- | --- | --- |
| Nonce failure denied | **PASS** | |
| Forged Inbox ID safe | **PASS** | |
| Array ID rejected | **PASS** | |
| Forged actor ignored | **PASS** | |
| Forged status ignored | **PASS** | |
| Stored preview cannot execute markup | **PASS** | |

---

## RESPONSIVE

| Check | Result | Notes |
| --- | --- | --- |
| 1440px | **PASS** | |
| 1280px | **PASS** | |
| 1024px | **PASS** | |
| 768px | **PASS** | |
| 375px | **PASS** | |
| No page-level overflow | **PASS** | |
| Table usable | **PASS** | |
| Actions usable on mobile | **PASS** | |

---

## REGRESSION

| Check | Result | Notes |
| --- | --- | --- |
| Referral 10 unchanged | **PASS** | |
| Phase 5A branding unchanged | **PASS** | |
| Terminology/modules unchanged | **PASS** | |
| Local Authority directory unchanged | **PASS** | |
| Management Dashboard unchanged | **PASS** | |
| Existing referrals unchanged | **PASS** | |
| Product remains 1.5.0 | **PASS** | |
| DB remains 2.31.0 | **PASS** | |
| Rewrite remains 1.2.8 | **PASS** | |
| No Outlook/Graph/Gmail connection | **PASS** | |

---

## Sign-off

| Role | Name | Date | Signature |
| --- | --- | --- | --- |
| Tester | | 2026-09-19 | Manual staging UAT **PASS** |
| Reviewer | | | |
