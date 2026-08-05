# Security — JM Referral System v1.0.0

Operational security overview for administrators and technical operators. Vulnerability **reporting** process is also summarised in the repository root `SECURITY.md`.

---

## Threat model (summary)

The plugin handles sensitive care and personal data inside WordPress. Trust boundaries:

- WordPress authentication and capability checks
- Plugin AccessPolicy for referral-scoped data
- Private filesystem storage for documents
- Admin and portal UIs (authenticated)
- Public intake form (unauthenticated, spam-controlled)

---

## Authentication and authorisation

- Staff features require a logged-in WordPress user
- Capabilities gate menus and actions (`jmrs_*`)
- `AccessPolicy` scopes Support Workers to assigned records
- Portal routes require portal entry capabilities and record checks
- Inaccessible referrals return a generic 404 (no existence leak)

---

## Documents

- New uploads: `uploads/jmrs-private/` with deny rules where supported
- Downloads: nonce-protected admin handlers — never publish raw paths
- Legacy Media Library files may remain publicly reachable until migrated/cleaned

---

## Public intake

- Nonce, honeypot, minimum completion time, hashed rate limit
- Private uploads only
- Consent fields are operational evidence, not a full consent platform

---

## Portal

- Privacy cache headers on authenticated portal pages
- Optional wp-admin redirect for non-administrator JM staff (test carefully)
- Secure downloads remain allowlisted when redirect is enabled

---

## Data protection practices

- Prefer HTTPS site-wide
- Least-privilege roles
- Backup DB + `jmrs-private` together
- Limit who can export CSV / reports
- Review `docs/DATA_RETENTION_POLICY.md` before permanent delete

---

## Reporting a vulnerability

**Do not** open a public GitHub issue for security flaws.

Email the maintainer / J&M Healthcare security contact (replace with production address before public distribution):

`newtontavengwa@gmail.com`

Include:

- Plugin version and WordPress/PHP versions
- Steps to reproduce
- Impact assessment
- Any proof-of-concept (non-destructive preferred)

### Disclosure policy

1. Acknowledge within a reasonable time (target: 5 business days)
2. Assess and remediate or mitigate
3. Coordinate public disclosure after a fix is available when appropriate
4. Credit researchers if desired and agreed

### Supported versions

| Version | Security updates |
| --- | --- |
| 1.0.x | Supported |
| &lt; 1.0 | Unsupported |

---

## Related documents

- Root `SECURITY.md`
- `docs/KNOWN_LIMITATIONS.md`
- `docs/BACKUP_AND_RECOVERY.md`
