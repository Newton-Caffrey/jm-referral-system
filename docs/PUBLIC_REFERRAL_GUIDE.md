# Public Referral Guide — JM Referral System v1.0.0

Guide for website editors and operations staff who publish the public referral form. End-user facing copy should match your organisation’s privacy notice.

---

## Purpose

Members of the public (or referrers) can submit a care referral from your website. Submissions create **real referrals** in JM Referral System with channel **website**.

Shortcode:

```
[jmrs_public_referral_form]
```

---

## Setup (editors / admins)

1. **Settings → Public Referral** — enable the form.
2. Configure:
   - Privacy policy URL
   - Consent version label
   - Notification email (ops)
   - Success message / next steps
   - Branding (company name, heading, intro, colours, contacts)
   - Optional document uploads and size/count limits
3. Create a WordPress page and paste the shortcode.
4. Publish and test on staging.

Technical detail: `docs/PUBLIC_REFERRAL_INTAKE.md`.

<!-- Screenshot: Settings → Public Referral -->

---

## Wizard experience

When JavaScript is available, the form is a multi-step wizard:

1. Welcome  
2. About You (referrer)  
3. Person (client)  
4. Care Needs  
5. Documents (optional)  
6. Review & Submit  

Without JavaScript, the full form remains usable as a single page.

Progress and review cards help users check details before submit. Analytics events (if listened for) expose **step number only** — no personal data.

<!-- Screenshot: wizard progress -->

---

## Documents

If uploads are enabled:

- Files go to **private** storage only (not the public Media Library)
- Allowed types and size limits are enforced
- Staff download later via secure plugin links

---

## Confirmation and reference numbers

After a successful submit:

- User is redirected to a receipt view on the same page (PRG pattern)
- A referral number / confirmation is shown when available
- Ops receive a notification email (if mail works)
- Referrer may receive a confirmation email

If mail is misconfigured, the referral may still be saved — check the admin referral list.

---

## Privacy and consent

- Consent checkboxes and version are operational evidence, not a full legal consent product
- Link a current privacy policy URL in settings
- Do not promise features the form does not provide (tracking portal, CAPTCHA, save-and-resume)

Spam controls include nonce, honeypot, minimum completion time, and hashed rate limiting (no raw IP storage in plugin options).

---

## For the public (suggested site copy)

Keep public help short:

- Who the form is for  
- What happens after submit  
- How you will be contacted  
- Link to privacy policy  
- Alternative phone/email for urgent safeguarding concerns  

---

## Related documents

- `docs/PUBLIC_REFERRAL_INTAKE.md`
- `docs/FAQ.md` (Public FAQ)
- `docs/TROUBLESHOOTING.md`
