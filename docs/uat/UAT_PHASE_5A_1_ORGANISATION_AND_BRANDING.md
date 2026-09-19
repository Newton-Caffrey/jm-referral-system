# UAT — Phase 5A.1 Organisation & Branding Foundation

**Development toward Product:** 1.6.0 (production-facing product version remains **1.5.0** until release)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Production baseline:** `93ee34ef02a958c86f80eca901fa5b6fe61c8237` / tag `v1.5.0`  
**Branch:** `develop/1.6.0`

**Scope:** Organisation and branding settings service, Settings UI sections, replacement of confirmed client-facing hard-coded organisation identity, JM-compatible defaults, focused manual UAT.

**Out of scope:** Email intake; Graph/Gmail/OAuth/IMAP/webhooks; Referral Inbox; DB schema / LA directory; module enable switches; configuration export/import; commercial product rename; production deploy/tag/package.

**Manual UAT date:** **2026-09-19**  
**Overall result:** **PASS**

---

## Precedence (source of truth)

```text
OrganisationSettings (jmrs_organisation_settings)
        ↓
Portal / Public / Management / Email presentation

Legacy fallbacks (upgrade only, when org option absent):
  PortalSettings (jmrs_staff_portal_settings)
  PublicReferralSettings (jmrs_public_referral_settings)
        ↓
Built-in JM Healthcare defaults
```

Saving Organisation & Branding syncs mirrored presentation fields into portal/public options so older readers stay coherent. Do not edit branding via the removed portal/public branding fields.

---

## ACTIVATION

| Check | Result | Notes |
| --- | --- | --- |
| Plugin activates | **PASS** | Activation smoke test |
| No fatal errors | **PASS** | |
| Product version remains 1.5.0 | **PASS** | |
| Database remains 2.29.0 | **PASS** | |
| Portal rewrite remains 1.2.7 | **PASS** | |

---

## DEFAULT UPGRADE BEHAVIOUR

| Check | Result | Notes |
| --- | --- | --- |
| Existing JM installation keeps existing branding without wizard | **PASS** | Preserved before configuration |
| Management Dashboard organisation name correct | **PASS** | Default branding |
| Staff portal branding correct | **PASS** | Default branding |
| Public intake branding correct | **PASS** | Default branding |
| Email sender identity remains sensible | **PASS** | |
| Existing PortalSettings / PublicBranding values not reset | **PASS** | |

---

## SETTINGS ACCESS

| Persona | Expected | Result | Notes |
| --- | --- | --- | --- |
| WordPress Administrator | Can open / save Organisation & Branding | **PASS** | |
| Platform Administrator (`jmrs_administrator`) | Can open / save | **PASS** | |
| Referral Manager | Denied (unless explicitly granted `jmrs_manage_settings`) | **PASS** | Denied |
| Care Coordinator | Denied | **PASS** | Denied |
| Assessor | Denied | **PASS** | Denied |
| Support Worker | Denied | **PASS** | Denied |

---

## ORGANISATION

| Check | Result | Notes |
| --- | --- | --- |
| Save display name | **PASS** | |
| Save legal name | **PASS** | |
| Save trading name | **PASS** | |
| Save contact email | **PASS** | |
| Save contact phone | **PASS** | |
| Save website | **PASS** | |
| Save address | **PASS** | |
| Save portal title | **PASS** | |

---

## BRANDING

| Check | Result | Notes |
| --- | --- | --- |
| Choose logo (Media Library) | **PASS** | |
| Change primary colour | **PASS** | |
| Change secondary / accent colour | **PASS** | |
| Change email sender display name | **PASS** | |
| Logo removal / fallback | **PASS** | No broken-image icon |

---

## VALIDATION / SECURITY

| Check | Result | Notes |
| --- | --- | --- |
| Invalid email rejected | **PASS** | |
| Unsafe URL rejected | **PASS** | |
| Invalid colour rejected | **PASS** | |
| Script / markup handled safely | **PASS** | Plain-text sanitisation |
| Array input rejected | **PASS** | |
| Nonce failure denied | **PASS** | |
| No stored XSS | **PASS** | |

---

## PROPAGATION

| Check | Result | Notes |
| --- | --- | --- |
| Portal reflects organisation name | **PASS** | |
| Dashboard reflects organisation name | **PASS** | |
| Public intake reflects organisation identity | **PASS** | |
| Configured logo appears where intended | **PASS** | |
| Outgoing test notification uses configured sender display name | **PASS** | |
| Platform Administrator role label displays correctly | **PASS** | |
| No referral data changes | **PASS** | |

---

## RESET / FALLBACK

| Check | Result | Notes |
| --- | --- | --- |
| Blank optional fields safely fall back | **PASS** | |
| Removing logo returns to text fallback (no broken image) | **PASS** | Covered under Branding |
| Existing routes unchanged | **PASS** | |

---

## RESPONSIVE

| Check | Result | Notes |
| --- | --- | --- |
| Settings ~1440px | **PASS** | |
| Settings ~1024px | **PASS** | |
| Settings ~768px | **PASS** | |
| Settings ~375px (no page-level horizontal overflow) | **PASS** | |
| Portal ~375px | **PASS** | |
| Dashboard ~375px | **PASS** | |
| Media selector usable | **PASS** | |

---

## REGRESSION

| Check | Result | Notes |
| --- | --- | --- |
| Referral 10 unchanged | **PASS** | |
| Dashboard counts unchanged | **PASS** | |
| Workflow unchanged | **PASS** | |
| Roles/capabilities unchanged (slug/caps); display label Platform Administrator | **PASS** | |
| Email / notification triggers unchanged | **PASS** | |
| Database remains 2.29.0 | **PASS** | |
| Rewrite remains 1.2.7 | **PASS** | |
| No email intake / Graph / Gmail / OAuth | **PASS** | |

---

## Accessibility (settings)

| Check | Result | Notes |
| --- | --- | --- |
| Associated labels | **PASS** | Covered in focused UAT |
| Field descriptions | **PASS** | |
| Clear headings (Organisation / Branding) | **PASS** | |
| Keyboard-accessible media selection | **PASS** | |
| Visible focus | **PASS** | |
| Validation error summary/notice | **PASS** | |
| No colour-only instructions | **PASS** | |

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Tester | Focused manual UAT | 2026-09-19 | **PASS** |
| Reviewer | | 2026-09-19 | **PASS** |
