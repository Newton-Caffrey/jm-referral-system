# Local Authority Directory (Phase 5A.3)

**Product:** 1.5.0 (unchanged)  
**Database:** **2.30.0** (additive from 2.29.0)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Branch:** `develop/1.6.0`

Platform configuration for commissioning organisations (Local Authorities) and **recognised sender** rules. This foundation is required for future automated email referral intake. **No mailbox connection** is implemented in this phase.

---

## Purpose

Authorised administrators can:

1. Maintain Local Authorities / commissioning organisations
2. Store basic contact information
3. Configure recognised sender email addresses (`exact_email`)
4. Configure recognised sender domains (`domain`)
5. Match a sender email against configured rules via a provider-neutral API
6. Preserve deterministic **MATCH** / **AMBIGUOUS** / **NO_MATCH** / **INVALID_SENDER** behaviour

---

## Security language

Sender rules mean **recognised / expected sender**, not verified authentic sender.

Email `From` addresses can be spoofed. Future email ingestion should also consider provider metadata / authentication signals (SPF/DKIM/DMARC, Graph authenticity headers, etc.) where available. **DMARC/SPF processing is not implemented in this phase.**

Do not describe rules as cryptographic verification.

---

## Schema (DB 2.30.0)

Physical names use `{wpdb->prefix}` (never hard-coded `wp_`).

### `{prefix}jmrs_local_authorities`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `name` | Required, plain text |
| `slug` | Required, unique, from `sanitize_title` |
| `status` | `active` \| `inactive` |
| `contact_name` / `contact_email` / `contact_phone` / `website` / `notes` | Optional |
| `created_at` / `updated_at` | MySQL datetime |

Indexes: unique `slug`; `status`; `name`.

### `{prefix}jmrs_local_authority_sender_rules`

| Column | Notes |
| --- | --- |
| `id` | PK |
| `local_authority_id` | Authority id (app-enforced referential integrity) |
| `rule_type` | `exact_email` \| `domain` |
| `rule_value` | Normalised lowercase value |
| `status` | `active` \| `inactive` |
| `created_at` / `updated_at` | MySQL datetime |

**Unique:** `(local_authority_id, rule_type, rule_value)` — duplicates per authority are rejected; the **same** rule value may exist under **different** authorities so the matcher can return **AMBIGUOUS**.

Indexes: `local_authority_id`, `rule_type`, `rule_value`, `status`, composite `(rule_type, rule_value, status)`.

No DB foreign keys (consistent with JMRS / `dbDelta`). Uninstall drops sender rules before authorities. Deactivating an authority does **not** delete rules.

**No referral schema change. No service schema change. No mailbox/token tables.**

---

## Architecture

| Class | Role |
| --- | --- |
| `LocalAuthorityRepository` | Authority CRUD / list / slug lookup |
| `SenderRuleRepository` | Rule CRUD / duplicates / active match queries |
| `LocalAuthorityService` | Validation, normalisation, activate/deactivate, overlap warnings |
| `LocalAuthoritySenderMatcher` | Provider-neutral `matchSender(string $email)` |
| `LocalAuthorityController` | Admin UI, capability `jmrs_manage_settings`, nonces |

### Matcher API

```php
$result = $localAuthorityService->matchSender('referrals@authority.gov.uk');
// status: NO_MATCH | MATCH | AMBIGUOUS | INVALID_SENDER
// authority, matched_rule, match_type, candidates (for AMBIGUOUS)
```

Input is a plain string email. Output is provider-neutral (no Graph/Gmail/OAuth objects).

### Matching precedence

1. Active **exact_email** rules on **active** authorities  
2. Else active **domain** rules on **active** authorities  

Domain match uses **label-boundary** semantics:

- `coventry.gov.uk` matches `referrals@coventry.gov.uk`
- and `referrals@adult.coventry.gov.uk` (subdomain)
- does **not** match `someone@coventry.gov.uk.attacker.com`

If rules from **more than one** authority match: **AMBIGUOUS** (never pick first row / lowest id).

Inactive authority or inactive rule → excluded from matching.

---

## Admin UI

Menu: **J&M Referrals → {Local Authority plural}** (from `TerminologySettings`).

Capability: `jmrs_manage_settings` (WP Administrator + Platform Administrator). Not granted to Referral Manager / Care Coordinator / Assessor / Support Worker by default.

Prefer activate/deactivate over delete for authorities. Rules support activate/deactivate; hard delete exists with nonce + confirm but is secondary.

Overlap warnings when the same active exact email/domain (or subdomain overlap) exists under another active authority — rules are not auto-merged or blocked solely for overlap.

---

## Module relationship

This directory is **platform configuration**. It is **not** gated by the `la_decisions` module. LA Decisions may be disabled while the directory remains available for intake/contact context.

---

## System audit

Phase 5A.1 found no suitable system-level configuration audit. Local Authority configuration events are **not** written to referral activity logs. Platform configuration audit remains a **future** requirement.

---

## Uninstall / retention

New tables are registered in `uninstall.php` drop order (rules before authorities). Default uninstall/data-retention behaviour is unchanged: wipe only when the existing destructive uninstall path runs. No newly destructive behaviour on deactivate.

---

## Out of scope (this phase)

Outlook, Microsoft Graph, Gmail, OAuth, webhooks, mailbox sync, Referral Inbox, AI classification, Expression of Interest, licensing, product bump to 1.6.0, main merge, tag, package, production deploy.
