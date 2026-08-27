# Known Limitations — JM Referral System v1.5.0

Genuine remaining limitations for the **1.5.0** release candidate. Do not treat this as a full backlog. JMRS supports role-based access, audit activity, retention workflows, and private-document handling; it does not itself certify GDPR, CQC, NHS, or medical-device compliance.

---

## Product decisions pending JM confirmation

| Limitation | Notes |
| --- | --- |
| Assessor package visibility | Assessor may **view** package panel amounts when the referral is visible — **existing product behaviour — requires future JM confirmation** |
| Assessor LA-decision visibility | Assessor may **view** LA decision panel (including notes) when the referral is visible — **existing product behaviour — requires future JM confirmation** |
| Assessor transition / placement visibility | Assessor may **view** transition/placement panel when the referral is visible — **existing product behaviour — requires future JM confirmation** |

---

## Acquisition / commercial reporting

| Limitation | Notes |
| --- | --- |
| PPV has no billing-frequency model | Proposed Package Value uses latest package `package_total` only — not annualised revenue |
| Authority analytics are free-text | Grouping depends on free-text authority fields; not a controlled authority register |
| Selected dashboard boards use row limits | Aggregate KPIs remain uncapped where implemented; some stage tables may truncate with a `rows_note` |
| Occupied-now vs future move-ins | Management Dashboard splits occupied-now from confirmed future move-ins; Homes vacancy treats future-dated active occupancy as unavailable (**known product semantic**) |
| No proposed-home reservation | No target-home / target-bedroom reservation workflow |
| No transition checklist | Transition readiness remains derived; no checklist entity |
| No reopen for terminal commercial records | Completed assessments, sent packages, recorded LA decisions, and care commencement are record-once / read-only for those workflows |

---

## Architecture & hosting

| Limitation | Notes |
| --- | --- |
| No background job queue | Schedule generation, exports, and alert calculation run in the HTTP request |
| No object cache integration | Service types / workflow stages / alerts are not cached across requests |
| Menu DI duplication | `Menu` may reconstruct repositories/services already built in `Plugin` — functional, not ideal |
| Multisite | Not specially optimized; uninstall/wipe is per-blog prefix; plan network installs carefully |
| Shared hosting timeouts | Very large generate windows or exports can still hit PHP/web timeouts despite batching |
| SMTP not bundled | Reliable notifications require working `wp_mail` / SMTP on the host |
| Private upload denial rules | Apache `.htaccess` may be written; nginx and some shared hosts need separate deny rules — **verify on target server** |
| Chart.js offline | Local `assets/vendor/chart.umd.min.js` preferred; audited tree falls back to jsDelivr when the local file is absent — verify in Phase 4J.1 |
| Management Google Fonts | Management Dashboard may load Google Fonts over the network where that enqueue runs |

---

## Data & documents

| Limitation | Notes |
| --- | --- |
| Legacy Media Library cleanup is manual | Settings migration copies to private storage; originals are **not** auto-deleted |
| No timed retention purge | Archive is manual; no automatic purge by age (legal/compliance sign-off required first) |
| No cascading clinical delete | By design — archive-first; permanent delete only when no blocking dependents |
| Document list unbounded on View | Documents/schedules/medication lists on Referral View are not paginated |
| Uninstall wipe is not production retention | Default uninstall **preserves** operational data; opt-in `JMRS_DELETE_DATA_ON_UNINSTALL` is administrative/development and is **not** the supported production retention workflow; complete purge coverage should be reviewed separately before relying on it |

---

## Performance (remaining)

| Limitation | Notes |
| --- | --- |
| Leading-wildcard referral search | `LIKE '%term%'` on multiple columns will not scale indefinitely |
| Report `DATE(column)` predicates | Some report/alert date filters may not use indexes optimally |
| Alert engine cost | Full rule fan-out on alerts/reports pages (dashboard reuses one run) |
| No cross-request report/alert cache | Each page/export recomputes |

---

## Product features not in scope

| Limitation | Notes |
| --- | --- |
| No automatic/cron visit generation | Generation is on-demand from the UI |
| No calendar UI | List/schedule forms only |
| No client or family portal | Staff admin + optional staff portal only |
| Staff portal disabled by default | Enable in Settings → Staff Portal; test before enabling wp-admin redirect |
| Public intake wizard has no save-and-resume | Values live in the DOM for the session; refresh clears unsaved input |
| Public intake: no CAPTCHA / tracking portal | Honeypot + rate limit + timing only |
| Consent checkboxes are operational evidence | Not a full legal consent-management product |
| No digital signatures | Not implemented |
| No external REST API | Not implemented |
| No automated test suite | Manual checklist in `docs/RELEASE_CHECKLIST.md` |
| Final software licence TBD | See root `LICENSE` placeholder; update before public distribution |
| Security contact | See `SECURITY.md` before public distribution |

---

## Accessibility

| Limitation | Notes |
| --- | --- |
| Browser `confirm()` dialogs | Used for destructive/confirm actions; not ideal for all AT tools |
| Charts are visual-first | Report charts accompanied by tables; canvas alone is not fully accessible |
| Dense Referral View | Long keyboard path through visit execution blocks |

See `docs/ACCESSIBILITY_REVIEW.md`.

---

## Supported Living reporting (v1.2 / Phase 2G)

| Limitation | Notes |
| --- | --- |
| Movement period uses recorded-at time | Placement movement KPIs/CSV filter on activity `created_at`, not backdated move-in/out dates |
| No home-specific historical movement filter | Activity has no structured Home IDs |
| Vacant Since = latest occupancy end | Uses latest recorded `move_out_date` (or Never occupied) |
| Monthly historical occupancy trend deferred | Current Snapshot / Vacancy are as-of-today only |
| Legacy visits without snapshots | Terminal/executed visits without location snapshots appear as Location Not Recorded |
| Support Workers lack estate-wide Homes/Reports | By design |
| Schedules have no independent location override | Service location resolves from care setting / occupancy / address |

---

## Explicit non-issues (by design)

- No foreign keys in MySQL (WordPress/`dbDelta` convention; integrity via app + retention checks)
- Deactivation does not delete data or roles
- Support Worker scoping is intentional, not a bug
- Champion / Transition Lead / meeting attendance do not grant AccessPolicy referral visibility
