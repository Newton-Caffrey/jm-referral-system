# Public Referral Intake

Frontend referral form for members of the public, families, hospitals, GPs, social workers, and partner organisations — without WordPress Admin access.

**Phases:** 6.1A foundation · **6.1B multi-step wizard** · **DB schema:** `2.17.0`

---

## Shortcode

```
[jmrs_public_referral_form]
```

Place on any normal WordPress page (for example `/make-a-referral/`). Theme-independent. CSS/JS load only when the shortcode is present.

Root wrapper: `.jmrs-public-referral` (scoped styles; CSS variables including `--jmrs-primary` from settings).

**Theme isolation (Phase 1.1F):** all public referral CSS is scoped under `.jmrs-public-referral` with a narrow component reset, explicit typography (`clamp()` where useful), and normalised form controls so host themes (Astra, Elementor, Kadence, etc.) cannot collapse field widths or override heading/button styles. Assets use `filemtime` cache-busting and enqueue at priority `100` so they follow typical theme stylesheets. After deploying CSS changes, purge production page/CDN caches.

---

## Wizard steps (Phase 6.1B)

| Step | Name | Purpose |
| --- | --- | --- |
| 0 | Welcome | Intro + Start Referral |
| 1 | About You | Referrer details |
| 2 | Person Needing Care | Client details |
| 3 | Care Needs | Service, priority, requirements |
| 4 | Documents | Optional private uploads |
| 5 | Review & Submit | Summary cards + consent + final send |

Progress shows the five user-facing steps (not Welcome). Labels: completed / current (`aria-current="step"`) / upcoming. Mobile: compact “Step X of 5” + progress bar.

Navigation: **Back** / **Continue**. Final button: **Send Referral to {Company Name}**.

JavaScript only controls step visibility. **One native HTML POST** still creates the referral. No AJAX. No partial server saves.

---

## No-JavaScript fallback

Without JS:

- All sections render visible in order (wizard panels are not hidden by default)
- Wizard-only controls stay `hidden`
- Native POST submit button remains available
- Server errors render with the error summary

JS adds `.jmrs-js` to activate step hiding.

---

## Branding settings

**J&M Referrals → Settings → Public Referral Intake**

| Setting | Default |
| --- | --- |
| Enable Public Referral Form | Off |
| Company Name | JM Healthcare |
| Public Referral Heading | Make a Referral |
| Public Referral Intro | 5–10 minute welcome copy |
| Contact phone / email | empty |
| Primary Brand Colour | `#0b5f4b` |
| Success Message | thank-you copy |
| Success Page Next-Steps Text | one item per line |
| Privacy Notice URL | empty |
| Consent Version | `1.0` |
| Notification Email | empty → WP admin email |
| Allow uploads / max count / max size | Off / 3 / 10 MB |

Accessor: `src/Frontend/PublicBranding.php`

---

## Conditional fields

Organisation shown for professional types: hospital, gp, social_worker, local_authority, care_provider, other.  
Hidden (but still in DOM) for self, family, friend — values still POST without JS.

---

## Validation

- **Continue:** client-side checks for the current step only (inline errors, `aria-invalid`, focus, `aria-live`)
- **Final submit:** server-side validation remains authoritative
- On server failure: earliest error step opens automatically; summary links to fields; not left on Welcome

---

## Success receipt (PRG)

After create succeeds:

1. Store transient `jmrs_pub_rcpt_{token}` with referral number + safe flags only (no form PII).
2. Redirect to the **page permalink** with `?jmrs_referral_receipt={token}`.
3. Shortcode checks the token **before** rendering the form → `templates/frontend/public-referral-success.php`.
4. Transient TTL **20 minutes**; not deleted on first view (refresh/print OK).
5. If storage fails: `?jmrs_referral_received=1` shows generic success without a number.
6. `nocache_headers()` when a receipt/received flag is present.

---

## Security (unchanged)

Nonce · honeypot · 3s minimum completion · 5/hour hashed rate limit (no raw IP) · sanitization · allowlists · private uploads · PRG receipt · no PII in URL/logs

---

## Analytics hooks (optional)

```js
document.addEventListener('jmrs:publicReferralStepChanged', (e) => {
  // e.detail.step — number only, no PII
});
document.addEventListener('jmrs:publicReferralSubmitted', () => {});
```

No provider integration yet.

---

## Manual tests

| Case | Expect |
| --- | --- |
| JS full flow | Steps 0→5, review, native POST, receipt |
| JS disabled | Full form visible; native POST works |
| Back/Continue | Values retained |
| Organisation conditional | Show/hide by referrer type |
| Per-step validation | Stay on step; focus first invalid |
| Server errors | Correct earliest step + summary |
| Review Edit | Returns to step; values kept |
| File summary | Names only; size/count checks |
| Double-click | Deferred disable; submitter preserved |
| Mobile progress | Compact step text + bar |
| Branding | Company name on button/success/email |
| Events | Step number only; no PII |

---

## Out of scope

AJAX submit · save/resume · CAPTCHA · tracking portal · public edit · accounts · payments · staff portal
