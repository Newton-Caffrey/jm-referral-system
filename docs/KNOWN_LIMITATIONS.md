# Known Limitations — JM Referral System v1.0.0

Genuine remaining limitations after Phase 5.6 readiness review. Do not treat this as a full backlog.

---

## Architecture & hosting

| Limitation | Notes |
| --- | --- |
| No background job queue | Schedule generation, exports, and alert calculation run in the HTTP request |
| No object cache integration | Service types / workflow stages / alerts are not cached across requests |
| Menu DI duplication | `Menu` may reconstruct repositories/services already built in `Plugin` (`PERF-H-008`) — functional, not ideal |
| Multisite | Not specially optimized; uninstall/wipe is per-blog prefix; plan network installs carefully |
| Shared hosting timeouts | Very large generate windows or exports can still hit PHP/web timeouts despite batching |

---

## Data & documents

| Limitation | Notes |
| --- | --- |
| Legacy Media Library cleanup is manual | Settings migration copies to private storage; originals are **not** auto-deleted |
| Hosts ignoring `.htaccess` | Private dir protection relies on Apache rules; nginx needs separate deny rules |
| No timed retention purge | Archive is manual; no automatic purge by age (legal/compliance sign-off required first) |
| No cascading clinical delete | By design — archive-first; permanent delete only when no blocking dependents |
| Document list unbounded on View | Documents/schedules/medication lists on Referral View are not paginated |

---

## Performance (remaining)

| Limitation | Notes |
| --- | --- |
| Leading-wildcard referral search | `LIKE '%term%'` on multiple columns will not scale indefinitely |
| Report `DATE(column)` predicates | Some report/alert date filters may not use indexes optimally |
| Alert engine cost | Full 11-rule fan-out on alerts/reports pages (dashboard reuses one run) |
| No cross-request report/alert cache | Each page/export recomputes |

---

## Product features not in v1.0

| Limitation | Notes |
| --- | --- |
| No automatic/cron visit generation | Generation is on-demand from the UI |
| No calendar UI | List/schedule forms only |
| No client or family portal | Staff admin only |
| Public intake wizard has no save-and-resume | Values live in the DOM for the session; refresh clears unsaved input |
| Public intake: no CAPTCHA / tracking portal | Honeypot + rate limit + timing only; see `docs/PUBLIC_REFERRAL_INTAKE.md` |
| Consent checkboxes are operational evidence | Not a full legal consent-management product |
| No digital signatures | Not implemented |
| No external REST API | Not implemented |
| No automated test suite | Manual checklist in `docs/RELEASE_CHECKLIST.md` |
| No Chart.js guarantee offline | Local vendor preferred; CDN fallback if vendor file missing |

---

## Accessibility

| Limitation | Notes |
| --- | --- |
| Browser `confirm()` dialogs | Used for destructive/confirm actions; not ideal for all AT tools |
| Charts are visual-first | Report charts accompanied by tables; canvas alone is not fully accessible |
| Dense Referral View | Long keyboard path through visit execution blocks |

See `docs/ACCESSIBILITY_REVIEW.md`.

---

## Operations

| Limitation | Notes |
| --- | --- |
| SMTP not bundled | Relies on host / WordPress mail configuration |
| WP_DEBUG query metrics | Optional generic counts when `WP_DEBUG` is true (no PHI/SQL) |
| Uninstall default preserves data | Opt-in wipe requires `JMRS_DELETE_DATA_ON_UNINSTALL === true` |
| InputAllowlist helper | Available utility; not all forms use it yet (local allowlists remain) |

---

## Explicit non-issues (by design)

- No foreign keys in MySQL (WordPress/`dbDelta` convention; integrity via app + retention checks)
- Deactivation does not delete data or roles
- Support Worker scoping is intentional, not a bug
