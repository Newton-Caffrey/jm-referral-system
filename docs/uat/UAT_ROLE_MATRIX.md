# UAT Role Matrix — JM Referral Platform v1.1.0

Use **fictional** WordPress users only. Store passwords in a private password manager — **never** in Git.

Capability source of truth: `src/Permissions/Roles.php` and `docs/developer/PERMISSIONS.md`. Support Workers are further restricted by `AccessPolicy` (assigned referrals / owned visits).

---

## Test user roster (fictional)

| Persona | Suggested username | Role slug | Notes |
| --- | --- | --- | --- |
| Public Referrer | *(none)* | Unauthenticated | Uses public shortcode page only |
| WordPress Administrator | `uat.wp.admin` | `administrator` | Full WP + all `jmrs_*` caps |
| JM Administrator | `uat.jm.admin` | `jmrs_administrator` | All JM capabilities |
| Referral Manager | `uat.manager` | `jmrs_referral_manager` | Ops + clinical; no settings/types/stages |
| Care Coordinator | `uat.coordinator` | `jmrs_care_coordinator` | Day-to-day clinical ops; no archive/delete/export/settings |
| Assessor | `uat.assessor` | `jmrs_assessor` | Assessment / care-plan focused; no dashboard / execute / schedules manage |
| Support Worker A | `uat.worker.a` | `jmrs_support_worker` | Display name: **John Testworker** |
| Support Worker B | `uat.worker.b` | `jmrs_support_worker` | Display name: **Rebecca Testworker** |

Suggested emails (fictional): `uat.wp.admin@example.test`, `uat.jm.admin@example.test`, `uat.manager@example.test`, `uat.coordinator@example.test`, `uat.assessor@example.test`, `john.testworker@example.test`, `rebecca.testworker@example.test`.

---

## Public Referrer

| Area | Expectation |
| --- | --- |
| Portal access | None |
| Menus | Public site page with referral form only |
| Referral visibility | Only own confirmation/receipt after submit (no staff list) |
| Allowed | Complete public wizard; attach allowed files if enabled; receive confirmation email when SMTP works |
| Forbidden | Staff portal; wp-admin; other clients’ data; raw storage paths |

---

## WordPress Administrator

| Area | Expectation |
| --- | --- |
| Portal access | Yes (all portal entry caps) |
| Menus | Portal: Dashboard, Referrals; wp-admin: full JM menus + Settings |
| Referral visibility | All referrals (not Support-Worker scoped) |
| Allowed | All JM actions including settings, service types, workflow stages, archive/restore, delete empty, reports, alerts |
| Forbidden | N/A within product — still must not expose private paths in UI |

---

## JM Administrator (`jmrs_administrator`)

| Area | Expectation |
| --- | --- |
| Portal access | Yes |
| Menus | Portal Dashboard + Referrals; wp-admin full JM including Settings |
| Referral visibility | All |
| Allowed | Same JM capability set as product “all caps” |
| Forbidden | Must not bypass private document download gates for users lacking AccessPolicy (test via other roles) |

---

## Referral Manager (`jmrs_referral_manager`)

| Area | Expectation |
| --- | --- |
| Portal access | Yes |
| Menus | Portal Dashboard + Referrals; wp-admin clinical + reports/alerts; **no** JM Settings / service types / workflow stages admin |
| Referral visibility | All |
| Allowed | Create/edit/assign; assessment; care plan + review; meds; care team; schedules; visits manage/execute/review; MAR; archive/restore; delete empty; export; alerts; reports |
| Forbidden | Manage Settings, service types, workflow stages |

---

## Care Coordinator (`jmrs_care_coordinator`)

| Area | Expectation |
| --- | --- |
| Portal access | Yes |
| Menus | Portal Dashboard + Referrals; wp-admin clinical ops + reports/alerts |
| Referral visibility | All |
| Allowed | Create/edit/assign; notes; documents; care plans + review; visits manage/execute; care team; schedules; meds + MAR; reports; alerts |
| Forbidden | Archive/restore; permanent delete; CSV export; Settings; service types; workflow stages |

---

## Assessor (`jmrs_assessor`)

| Area | Expectation |
| --- | --- |
| Portal access | Limited — **no** `jmrs_view_dashboard` (portal entry may still succeed via other caps such as view referrals; Dashboard nav item hidden) |
| Menus | Portal Referrals (if portal entry allowed); no Dashboard nav; no visit execute/manage |
| Referral visibility | All (not scoped) |
| Allowed | Edit referrals; notes; documents; view/manage care plans; review care plans; view visits/team/schedules; view/manage medications |
| Forbidden | Create referral (no create cap); assign; archive/restore; delete; export; manage visits; execute visits; manage care team; manage schedules; administer meds; reports; alerts; settings |

---

## Support Worker A / B (`jmrs_support_worker`)

| Area | Expectation |
| --- | --- |
| Portal access | Yes — Dashboard + **My Referrals** |
| Menus | Portal only for day-to-day; wp-admin redirect may apply if enabled (leave **off** until UAT of redirect) |
| Referral visibility | **Only assigned** referrals / AccessPolicy scope |
| Allowed | View assigned referral summaries; download docs when permitted; view care plan/team/schedules/meds; **execute own visits**; administer medications on owned visits when shown; add notes |
| Forbidden | Edit referral; assessment/care-plan manage; care-plan review; manage meds list; manage care team; manage schedules; create/edit visits (manage); manager review; archive/restore; reports; alerts; settings; **other workers’ visits**; referrals not assigned to them |

### Worker A vs B (mandatory)

- Referral assigned to **John Testworker (A)** must be invisible / generic 404 for **Rebecca Testworker (B)** when B is not on the care team / assignment scope.
- Visit assigned to A: B cannot execute.
- After reassignment to B: A loses execute/access where policy requires; B gains.

---

## Quick permission grid (portal / clinical)

Legend: ✓ allowed · ✗ forbidden · ~ view-only / scoped

| Action | WP Admin | JM Admin | Manager | Coordinator | Assessor | Worker |
| --- | --- | --- | --- | --- | --- | --- |
| Portal dashboard | ✓ | ✓ | ✓ | ✓ | ✗ nav | ✓ |
| Referral view (all) | ✓ | ✓ | ✓ | ✓ | ✓ | ~ assigned |
| Referral edit | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Assessment | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Care plan manage | ✓ | ✓ | ✓ | ✓ | ✓ | ~ view |
| Care plan review | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Manage medications | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Administer MAR | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ own visit |
| Manage care team | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Manage schedules | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Manage visits | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Execute visits | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ owned |
| Manager visit review | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Archive / restore | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| Reports / alerts | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Settings | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |

Archived referrals: mutations forbidden for all roles; summaries remain visible where view is allowed.

---

## Notes for testers

- Prefer **portal** URLs for staff clinical UAT of v1.1; use wp-admin to confirm regression parity.
- Generic **404** for inaccessible referral IDs (no existence leak).
- Do not enable “redirect staff away from wp-admin” until redirect-specific cases pass.
