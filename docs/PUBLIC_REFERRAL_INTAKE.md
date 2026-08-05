# Public Referral Intake (Phase 6.1A)

Frontend referral form for members of the public, families, hospitals, GPs, social workers, and partner organisations — without WordPress Admin access.

**Plugin version context:** 1.0.x feature phase · **DB schema:** `2.17.0` (public intake columns)

---

## Shortcode

```
[jmrs_public_referral_form]
```

Place on any normal WordPress page (for example `/make-a-referral/`). Theme- and Elementor-independent. CSS/JS load only when the shortcode is present.

Root wrapper class: `.jmrs-public-referral` (scoped styles; CSS variables for colours).

---

## Settings

**J&M Referrals → Settings → Public Referral Intake**

| Setting | Default |
| --- | --- |
| Enable Public Referral Form | Off |
| Privacy Notice URL | empty |
| Public Consent Version | `1.0` |
| Public Referral Notification Email | empty → falls back to WP admin email |
| Success Message | thank-you copy |
| Allow Public Document Uploads | Off |
| Maximum Public Upload Count | 3 |
| Maximum Public Upload Size | 10 MB |

---

## Form sections

1. **About the referrer** — type, name, organisation, email, phone, relationship  
2. **Client details** — names, contact, DOB, address  
3. **Care requirements** — service type, start date, preferred contact, priority (`routine` / `urgent`), requirements, additional info, optional uploads  
4. **Consent** — permission, assessment use, privacy notice  

Public priority maps to internal priority: `routine` → `medium`, `urgent` → `urgent`.

Internal fields are **not** exposed: assignee, workflow stage, status, internal notes UI, care plan, staff fields.

---

## Creation mapping

`PublicReferralService` validates/sanitizes public input, then calls existing `ReferralService::create()`:

| Public | Stored |
| --- | --- |
| First + last name | `client_name` (+ split columns) |
| — | `submission_channel` = `public_website` |
| — | `referral_source` = `website` |
| — | `status` = `new` |
| — | default workflow stage (New Referral) |
| — | `assigned_to` = NULL |
| Consents | `public_consent_at`, `public_consent_version` |
| Additional information | `notes` |

Activity logging and referral numbering use the normal create path.

---

## Security

- WordPress nonce  
- Server-side validation / sanitization / allowlists  
- Honeypot (`jmrs_website`)  
- Minimum completion time (3 seconds)  
- Rate limit: 5 submissions / hour per hashed fingerprint (IP + UA + action) — **raw IP is not stored**  
- Generic error messages for abuse paths  
- No login required  

Failed spam attempts are **not** stored as referrals. Optional `WP_DEBUG` logs are event-only (no PHI).

---

## Uploads

When enabled: PDF/DOC/DOCX/JPG/JPEG/PNG into **private** `uploads/jmrs-private/` only. Never Media Library. Uploads run **after** referral create; partial upload failure keeps the referral and shows a safe message.

---

## Notifications

1. **Ops inbox** — settings email (or admin email): summary + secure View link (`public-referral-received`)  
2. **Referrer confirmation** — only if referrer email supplied (`public-referral-confirmation`)  

Care requirements are not emailed in full to ops; use the admin View link.

---

## Success flow

Post → Redirect → Get with opaque receipt token (transient). Refresh does not resubmit. Shows reference number, configured message, next steps, Print/Save. No sensitive submitted fields on the success screen.

---

## Admin / reports

- Referral View: Submission Channel; for website referrals also referrer type, organisation, relationship, consent date/version, address/DOB summary  
- List: Website badge under Source when channel is public  
- CSV export includes public-intake columns (formula protection preserved)  
- Counts, source analytics, alerts, archive/restore use the same referral records and AccessPolicy  

Consent fields are **operational evidence**, not a complete legal consent-management system.

---

## Manual test matrix

| Case | Expect |
| --- | --- |
| Form disabled | Message; no accept |
| Valid family / professional | Creates referral; channel Website; source Website |
| Missing required / bad email/date/options | Field errors; focus summary |
| Honeypot / too-fast / rate limit | Generic error; no referral |
| Success refresh | Same receipt; no duplicate |
| Notification + confirmation emails | Ops + referrer when email set |
| Uploads off / valid / invalid / oversized / partial | Per settings; referral kept on partial fail |
| Admin display / CSV / alerts / archive | Public fields visible; retention unchanged |
| Theme / mobile / keyboard | Scoped CSS; labels; error focus |

---

## Out of scope (later phases)

Multi-step wizard (6.1B), CAPTCHA, tracking portal, public edit, referrer accounts, payments, signatures, eligibility automation, staff frontend portal.
