# Staff Portal — Phase 6.2A Foundation

Secure, branded frontend portal for JM staff. Reuses existing JMRS services, repositories, capabilities, and `AccessPolicy`. Does **not** replace WordPress Admin.

**Defaults:** portal **disabled**; wp-admin redirect **disabled**. Enable under **J&M Referrals → Settings → Staff Portal**.

---

## Architecture

| Layer | Role |
| --- | --- |
| `PortalRouter` | Rewrite rules + query vars; `template_redirect` dispatch (theme-independent) |
| `PortalController` | Auth gates, view models, privacy headers, template include |
| `PortalAccess` | Portal eligibility, login/logout redirects, admin bar, optional wp-admin redirect |
| `PortalNavigation` | Capability-based nav (no role-name checks for menu items) |
| `PortalSettings` / `PortalUrls` / `PortalAssets` | Options, URLs, CSS/JS scoped to portal routes |

Templates receive prepared view models only (no SQL, no scattered capability logic).

### Theme isolation (Phase 1.1F)

- Root: `.jmrs-portal` / body class `jmrs-portal-body` (via `body_class()` so `admin-bar` applies when visible)
- Host theme, block library, and global styles are dequeued on portal routes; WordPress admin-bar CSS is retained
- Sticky sidebar, topbar, and mobile nav toggle use `--jmrs-admin-bar-offset` (32px / 46px)
- Portal CSS/JS versioned with `filemtime` — purge host/CDN caches after deploy
---

## Routes

Configurable base path (default `staff-portal`):

| URL | Route |
| --- | --- |
| `/staff-portal/` | Dashboard |
| `/staff-portal/referrals/` | Referral list |
| `/staff-portal/homes/{id}/` | Home operational dashboard (residents, bedrooms, upcoming visits, attention) |
| `/staff-portal/referrals/{id}/` | Referral view (incl. Care Setting / Own-Home / Supported Living panels) |
| `/staff-portal/referrals/{id}/edit/` | Referral edit (incl. care setting) |
| `/staff-portal/referrals/{id}/assessment/` | Assessment create/edit |
| `/staff-portal/referrals/{id}/care-plan/` | Care plan create/edit |
| `/staff-portal/referrals/{id}/care-plan/review/` | Care plan review |
| `/staff-portal/referrals/{id}/medications/new/` | Add medication |
| `/staff-portal/referrals/{id}/medications/{medication_id}/edit/` | Edit medication |
| `/staff-portal/referrals/{id}/care-team/new/` | Add care-team member |
| `/staff-portal/referrals/{id}/care-team/{assignment_id}/edit/` | Edit care-team member |
| `/staff-portal/referrals/{id}/schedules/new/` | Create schedule |
| `/staff-portal/referrals/{id}/schedules/{schedule_id}/edit/` | Edit schedule |
| `/staff-portal/referrals/{id}/schedules/{schedule_id}/generate/` | Generate visits |
| `/staff-portal/referrals/{id}/visits/new/` | Schedule visit |
| `/staff-portal/referrals/{id}/visits/{visit_id}/edit/` | Edit visit |
| `/staff-portal/referrals/{id}/visits/{visit_id}/execute/` | Execute visit (+ MAR) |
| `/staff-portal/referrals/{id}/visits/{visit_id}/review/` | Manager visit review |

Rewrite flush occurs on plugin activation/deactivation and when portal enable/base path/rewrite version changes — **not** on every request.

---

## Authentication

- Requires a logged-in WordPress user (no custom auth).
- Unauthenticated visitors → `wp_login_url( redirect_to = requested portal URL )`.
- After login: JM staff → portal dashboard (or `redirect_to` if it is a portal URL). WordPress Administrators keep normal redirect unless `redirect_to` is a portal URL.
- Logout → site home.

---

## Portal access

Users need at least one portal entry capability (or `manage_options`), for example:

- `jmrs_view_dashboard`
- `jmrs_view_referrals`
- `jmrs_view_visits`
- `jmrs_view_care_plans`
- `jmrs_view_reports`
- (also visits/care-team/schedules/medications/alerts caps)

No portal capability → branded **403**.

Record routes use `AccessPolicy::can_view_referral()`. Missing or inaccessible referrals return a generic **404** (no existence leak).

---

## Navigation (6.2A)

Capability-driven. Only implemented routes are shown:

| Role pattern | Typical nav |
| --- | --- |
| Support Worker (scoped) | Dashboard, My Referrals |
| Care Coordinator / Referral Manager | Dashboard, Referrals |
| JM / WP Administrator | Dashboard, Referrals (full caps) |

Future items (assessments, care plans, MAR, reports, etc.) are **hidden** until portal routes exist.

---

## wp-admin restriction

Optional setting: **Redirect JMRS Staff Away From wp-admin**.

- Does **not** apply to WordPress Administrators.
- Redirects ordinary wp-admin screens to the portal.
- Allows: `admin-ajax.php`, `admin-post.php`, `async-upload.php`, document download (`jmrs_download_document`), CSV export (`jmrs_export`), profile screens, cron.
- Hides the admin bar on the frontend for restricted JM staff; Administrators keep it if desired.

---

## Current scope

| Area | Portal behaviour |
| --- | --- |
| Dashboard | Scoped widgets; upcoming/awaiting/completed visits link to portal execute/review; Recent Referrals show View / Edit / Archive / Restore when permitted |
| Referral list | Search/filters/pagination; Active / Archived / All scope; Edit / Archive / Restore when permitted |
| Referral view | Summaries + secure downloads; Care Setting + current service location; contextual clinical actions (review care plan, meds, care team, schedules, visits); archived remains read-only |
| Referral edit | Shared `ReferralService::update()` pipeline including client address (own-home service location) |
| Assessment / Care Plan | Shared `attempt_*` pipelines; PRG to referral view |
| Care plan reviews | `jmrs_review_care_plans` + AccessPolicy; history on referral view |
| Medications | Add/edit via status field (pause/discontinue); Support Workers read-only |
| Care team | Add/edit; primary-carer rules unchanged |
| Schedules | List + create/edit + generate visits; current service location shown (informational) |
| Visits | Create/edit/execute/review with current vs historical service location panels |
| Shared services | Portal handlers call the same admin controller `attempt_*` methods (no forked validation) |

WordPress Admin UI remains fully available for administrators and when redirect is off.

---

## Security headers

Authenticated portal pages send:

- `Cache-Control: private, no-store, no-cache, must-revalidate`
- `Pragma: no-cache`
- `Expires: 0`
- `X-Content-Type-Options: nosniff`

Plus `nocache_headers()`.

---

## Settings

**J&M Referrals → Settings → Staff Portal**

- Enable Staff Portal
- Portal Name / Company Name / Base Path / Logo
- Primary / Secondary Colour
- Support Email / Phone
- Login Redirect URL
- Redirect JMRS Staff Away From wp-admin

Path conflict with an existing WordPress page shows a warning after save.

---

## Accessibility

Skip link, landmarks, semantic nav with `aria-current`, labelled mobile menu, keyboard-operable drawer, visible focus, responsive tables, logical headings. No formal WCAG claim.

---

## Phase 1.1D — UX polish

Presentation-only pass over the existing portal shell and pages. No new routes, permissions, or business data.

- Reusable partials under `templates/portal/partials/` (`notice`, `empty-state`, `section-header`, `client-summary`, `kpi-card`)
- Sidebar nav grouped by section ("Overview" / "Care") with decorative icons; expanded current-route matching so all referral-scoped clinical pages highlight **Referrals**
- Dashboard: welcome header (staff name + role), KPI cards, "Today's Schedule" framing for upcoming visits, helpful empty states for awaiting-review/completed sections, "Recent Activity" framing for recent referrals
- Referral view: client summary header, quick-actions bar built from existing permission-gated URLs, consistent section-card headers, activity table converted to a vertical timeline, empty states with calls-to-action where staff can act
- Buttons show a loading spinner (`.is-loading` / `[aria-busy="true"]`) on submit and duplicate submit buttons are disabled while a request is in flight; the submitter itself keeps its `name`/`value` so server-side `attempt_*` dispatch is unaffected
- Refined spacing scale, focus states (including `select`/`textarea`), and tablet (`~1024px`) / mobile (`~768px`) breakpoints

---

## UAT for Supported Living / v1.2.0

Formal acceptance testing for Supported Living is documented in [`docs/uat/UAT_SUPPORTED_LIVING_V1_2.md`](uat/UAT_SUPPORTED_LIVING_V1_2.md). Reporting subset: [`docs/uat/UAT_SUPPORTED_LIVING_REPORTING.md`](uat/UAT_SUPPORTED_LIVING_REPORTING.md). **v1.2.0 production release requires UAT + `docs/RELEASE_CHECKLIST.md` gate** after mandatory scenarios pass.

---

## Manual test checklist

- Portal disabled → routes unavailable / 404 after flush
- Unauthenticated → login + return to portal URL
- WP Administrator / JM Administrator / Referral Manager / Care Coordinator / Assessor / Support Worker access and nav
- User with no JMRS caps → 403
- wp-admin redirect on/off; AJAX, admin-post, download, export still work
- Dashboard scoping (manager vs support worker)
- List filters, pagination, page size; Support Worker has no assignee filter
- Direct accessible referral URL; inaccessible / missing → generic 404
- Archived referral view (read-only + archive notice)
- Secure document download under redirect
- Mobile + keyboard navigation
- Cache / privacy headers present
- Base path change flushes rewrites once
- No theme header/footer; no admin/Chart.js/wizard assets on portal
- Theme stylesheets are dequeued on portal routes (admin-bar retained); sticky sidebar/topbar offset for WP admin bar
- Care-plan review: add review, status/next-review effects, Support Worker read-only
- Medications: add/edit/pause/discontinue via status; permission gates; inactive display
- Care team: add/edit; primary enforcement; inactive members
- Schedules: create/edit; weekday persistence; generate; duplicates; paused/completed rules
- Visits: create/edit; staff filtering; execute (owner-only for Support Worker); tasks; MAR validation; duplicate execute blocked
- Manager review: eligible manager only; Support Worker denied; dashboard awaiting-review updates
- Archived referral: summaries visible; all portal mutation actions hidden; forged POSTs rejected
- Equivalent wp-admin workflows still work (regression)

---

## Known limitations

- No portal reports or operational-alerts page (indicator only when permitted)
- No custom login design
- No portal notes UI or document upload UI (downloads work)
- No referrer/family portal, REST API, or mobile app
- No AJAX-only / modal editing for clinical forms

---

## Future portal phases

- Portal notes & document uploads
- Reports & operational alerts pages
- Optional branded login
- Referrer / family portals (separate products)
