# Contributing — JM Referral System

Thank you for helping improve the JM Referral System. This project is operated for J&M Healthcare; external contributions may be limited until licensing is finalised.

---

## Coding standards

- PHP: WordPress coding conventions where practical; PSR-4 under `JMReferral\` → `src/`
- Prefer Repository → Service → Controller → Template
- Escape all template output; capability + AccessPolicy on sensitive actions
- No SQL in templates; no PHI in logs
- Do not add features on release-engineering branches without agreement
- Match existing naming: `jmrs_` prefixes for options, nonces, query args, capabilities

### React / front-end assets

Admin and portal JS should remain small and dependency-light unless the team adopts a build pipeline.

---

## Branch naming

| Pattern | Use |
| --- | --- |
| `main` / `master` | Stable release line |
| `release/1.0.0` | Release packaging |
| `feature/<short-name>` | New capability |
| `fix/<short-name>` | Bug fix |
| `docs/<short-name>` | Documentation only |

---

## Commit messages

- Imperative mood, concise subject (≤ ~72 chars)
- Explain **why** when not obvious
- Examples:
  - `fix portal medication field keys for admin parity`
  - `docs: add v1.0.0 installation guide`

Do not commit secrets, `.env`, or production dumps.

---

## Pull requests

- Describe scope, test plan, and risk
- Link related issues
- Keep PRs focused (docs vs features vs fixes)
- Ensure `vendor/` remains installable (`composer install --no-dev` for release ZIPs)

---

## Issue reporting

Include:

- Plugin version (`1.0.0`) and `jmrs_db_version`
- WordPress / PHP versions
- Role of the user
- Steps to reproduce
- Expected vs actual

**Security issues:** do not file public issues — follow `SECURITY.md`.

---

## Local setup

1. WordPress + MySQL  
2. Clone plugin into `wp-content/plugins/jm-referral-system`  
3. `composer install` if `vendor/` is missing  
4. Activate plugin  
5. Use staging data only  

See `docs/INSTALLATION_GUIDE.md`.
