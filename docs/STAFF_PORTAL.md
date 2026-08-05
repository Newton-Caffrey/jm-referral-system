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

---

## Routes

Configurable base path (default `staff-portal`):

| URL | Route |
| --- | --- |
| `/staff-portal/` | Dashboard |
| `/staff-portal/referrals/` | Referral list |
| `/staff-portal/referrals/{id}/` | Referral view (read-only) |

Rewrite flush occurs on plugin activation/deactivation and when portal enable/base path changes via settings — **not** on every request.

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

## Current read-only scope

| Area | Portal behaviour |
| --- | --- |
| Dashboard | Scoped widgets via existing services |
| Referral list | Search/filters/pagination; View only; no archive/delete |
| Referral view | Summaries + secure document downloads; no edit/notes/upload/stage/MAR/visit execution |

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

---

## Known limitations (6.2A)

- No custom login design
- No frontend edit, notes, uploads, assessments, care-plan editing, scheduling, visit execution, MAR, or reports
- No referrer/family portal, REST API, or mobile app
- Operational Alerts / Reports not yet portal routes (indicator only when permitted)
- Edit remains wp-admin-only (Edit hidden in portal)

---

## Future portal phases

- Frontend referral edit / notes / documents
- Assessments & care plans
- Scheduling & visit execution
- Medication administration (MAR)
- Reports & operational alerts pages
- Optional branded login
- Referrer / family portals (separate products)
