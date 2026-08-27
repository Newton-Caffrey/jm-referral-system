# UAT — Phase 4I.1 Management Operations Dashboard UI/UX Polish

**Product:** 1.4.0 (unchanged)  
**Database:** 2.29.0 (no migration)  
**Portal rewrite:** 1.2.7 (unchanged)  
**Baseline checkpoint:** `b4e2c4f98b2a32cc88cce3cf5e45f5e3e9539fd7` (Phase 4H.1)

**Scope:** Visual-only polish of the Management Dashboard, especially the Operations tab. Shared `.jmrs-mgmt` spacing and component system for Workflow Stage Distribution, Assessments, Package Costing, Local Authority Decisions, Recent Referrals, and Recent Activity.

**Out of scope:** Schema/migrations; product/DB/rewrite version bumps; routes; capabilities; AccessPolicy; SQL/queries; metric definitions; counts/limits/sorting; workflow/pipeline/status behaviour; package-cost / LA-decision / assessment / transition / occupancy / care-commencement behaviour; activity text; email; privacy rules; new metrics/charts/filters; external libraries/fonts/CDN; sample data.

**Manual visual UAT date:** **2026-08-27**  
**Overall result:** **PASS**

---

## Design summary

| Area | What changed visually |
| --- | --- |
| Shared spacing | Scoped tokens under `.jmrs-mgmt` (`--jmrs-mgmt-pad-*`, `--jmrs-mgmt-gap-*`, `--jmrs-mgmt-space-*`) |
| Section containers | `.jmrs-mgmt__ops-body` padding; title/subtitle separated from body |
| Workflow stages | Row = label + count; bar beneath; taller rounded tracks; `.is-zero` muted (not hidden) |
| Assessments | 3-col KPI grid; outcome row spacing; Upcoming/Past `.ops-block` subgrid |
| Package Costing | 4→2→1 KPI grid; asymmetric Prepared / Sent subgrid; table cell padding |
| LA Decisions | 4→2→1 KPI grid; matching subsection blocks |
| Recent Referrals | Table padding; text+colour status badges (`new` / `in_progress` / `completed` / `cancelled` / neutral) |
| Recent Activity | Ref / description / quieter meta line; entry padding |
| Card heights | Recent pair uses `align-items: start` (natural height) |
| Empty states | Compact muted intentional empty areas |
| Print | Status badges monochrome-readable; table overflow visible |

**No data/query/permission changes.** Presentation-only `status_key` added for badge CSS class mapping; displayed status labels, stored status values, queries, sorting, and limits unchanged.

---

## Activation and baseline

| Check | Result |
| --- | --- |
| Plugin activation smoke test | **PASS** |
| Management Dashboard opened | **PASS** |
| Operations tab opened | **PASS** |
| Product remained 1.4.0 | **PASS** |
| Database remained 2.29.0 | **PASS** |
| Portal rewrite remained 1.2.7 | **PASS** |
| Dashboard counts remained unchanged | **PASS** |

---

## Responsive viewports

Layout was **manually reviewed** at each width.

| Width | Result |
| --- | --- |
| 1440px layout | **PASS** |
| 1280px layout | **PASS** |
| 1024px layout | **PASS** |
| 768px layout | **PASS** |
| 375px layout | **PASS** |

---

## Workflow Stage Distribution

| Check | Result |
| --- | --- |
| Section padding improved | **PASS** |
| Heading and subtitle spacing improved | **PASS** |
| Workflow label and count alignment | **PASS** |
| Progress bars displayed beneath label rows | **PASS** |
| Progress tracks visually clearer | **PASS** |
| Rounded tracks displayed correctly | **PASS** |
| Zero-value stages remained visible | **PASS** |
| Zero-value stages were visually muted | **PASS** |
| No workflow stage hidden | **PASS** |
| No count changed | **PASS** |
| Long stage labels wrapped safely | **PASS** |
| Progress meaning remained accessible without colour alone | **PASS** |

---

## Assessments section

| Check | Result |
| --- | --- |
| Assessment KPI layout | **PASS** |
| KPI cards aligned consistently | **PASS** |
| KPI padding and card heights | **PASS** |
| Outcome distribution spacing | **PASS** |
| Outcome labels and counts aligned | **PASS** |
| Upcoming and Past subsection layout | **PASS** |
| Empty-state presentation | **PASS** |
| Responsive stacking | **PASS** |
| Assessment counts and data unchanged | **PASS** |
| Assessment privacy unchanged | **PASS** |

---

## Package Costing section

| Check | Result |
| --- | --- |
| Four-card desktop KPI layout | **PASS** |
| Fourth KPI no longer wrapped unnecessarily | **PASS** |
| Two-column intermediate layout | **PASS** |
| Single-column mobile layout | **PASS** |
| KPI card heights and padding | **PASS** |
| Package Costing subsection balance | **PASS** |
| Sent Packages received sufficient width | **PASS** |
| Prepared Packages empty state compact and intentional | **PASS** |
| Package table spacing | **PASS** |
| Table borders and row readability | **PASS** |
| Referral references wrapped safely | **PASS** |
| Open links remained readable and usable | **PASS** |
| Table-contained horizontal scrolling | **PASS** |
| Package counts and links unchanged | **PASS** |
| No package totals, recipients, references or filenames exposed | **PASS** |

---

## Local Authority Decisions

| Check | Result |
| --- | --- |
| Local Authority Decision section padding | **PASS** |
| Four-card desktop KPI layout | **PASS** |
| Responsive 4 → 2 → 1 behaviour | **PASS** |
| KPI card spacing and height | **PASS** |
| List and empty-state spacing | **PASS** |
| LA Decision privacy | **PASS** |
| No decision notes exposed | **PASS** |
| No funding details exposed | **PASS** |
| No decision references exposed | **PASS** |
| No package values exposed | **PASS** |
| Decision counts unchanged | **PASS** |

---

## Recent Referrals

| Check | Result |
| --- | --- |
| Recent Referrals section spacing | **PASS** |
| Heading-to-table spacing | **PASS** |
| Table header padding | **PASS** |
| Table body-cell padding | **PASS** |
| Row readability improved | **PASS** |
| Referral references remained readable | **PASS** |
| View links remained unchanged | **PASS** |
| Status badges displayed | **PASS** |
| Badges retained visible status text | **PASS** |
| Known status styling | **PASS** |
| Unknown or legacy status neutral fallback | **PASS** |
| Colour was not the only source of status meaning | **PASS** |
| Row count, order and links remained unchanged | **PASS** |

**`status_key`:** presentation-only for badge CSS classes. Does **not** change stored status values, query semantics, sorting, limits, or displayed labels.

---

## Recent Activity

| Check | Result |
| --- | --- |
| Recent Activity spacing | **PASS** |
| Activity-entry padding | **PASS** |
| Referral reference hierarchy | **PASS** |
| Activity-description readability | **PASS** |
| Actor · date metadata line displayed correctly | **PASS** |
| Metadata visually quieter | **PASS** |
| Long activity descriptions wrapped safely | **PASS** |
| Entry separators remained subtle | **PASS** |
| Activity order unchanged | **PASS** |
| Activity text unchanged | **PASS** |
| Actor and date values unchanged | **PASS** |
| Activity privacy unchanged | **PASS** |

---

## Natural card heights

| Check | Result |
| --- | --- |
| Recent Referrals and Recent Activity aligned at the top | **PASS** |
| Natural card heights used | **PASS** |
| Recent Referrals was not stretched to Recent Activity height | **PASS** |
| No large unnecessary empty area remained | **PASS** |
| No fixed desktop height introduced | **PASS** |
| Cards stacked correctly on smaller screens | **PASS** |

---

## Tables, overflow and mobile

| Check | Result |
| --- | --- |
| Table-contained horizontal scrolling | **PASS** |
| No page-level horizontal overflow | **PASS** |
| No clipped meaningful content | **PASS** |
| No clipped links | **PASS** |
| No clipped focus indicators | **PASS** |
| Tables remained semantic HTML tables | **PASS** |
| Table headers retained `scope` attributes | **PASS** |
| Long references wrapped safely | **PASS** |
| Activity messages wrapped safely | **PASS** |
| KPI cards became one column at mobile width | **PASS** |
| Section padding remained visible at 375px | **PASS** |
| No content touched the viewport edges | **PASS** |

**`overflow-x: clip` on `.jmrs-mgmt`:** Reviewed — does **not** hide meaningful dashboard content, shadows, focus rings, links, or table edges. Table scrolling remains inside `.jmrs-mgmt__tbl-scroll`.

---

## Accessibility

| Check | Result |
| --- | --- |
| Keyboard focus | **PASS** |
| Focus-visible styles remained visible | **PASS** |
| Dashboard tabs remained keyboard accessible | **PASS** |
| View and Open links remained keyboard accessible | **PASS** |
| Links inside table scroll containers remained reachable | **PASS** |
| Heading hierarchy | **PASS** |
| Colour-independent meaning | **PASS** |
| Workflow zero counts remained available as text | **PASS** |
| Status badges contained text | **PASS** |
| Progress values retained accessible text or labels | **PASS** |
| No hover-only information introduced | **PASS** |
| No meaningful screen-reader content hidden | **PASS** |

---

## Print preview

| Check | Result |
| --- | --- |
| Print preview | **PASS** |
| Sections did not waste excessive printed space | **PASS** |
| Tables were not clipped by scroll containers | **PASS** |
| Status badges remained readable in monochrome | **PASS** |
| Workflow stages and counts remained understandable | **PASS** |
| Cards avoided awkward splitting where practical | **PASS** |
| Background colour was not required to understand meaning | **PASS** |

---

## Functional regression

| Check | Result |
| --- | --- |
| Recent Referral links opened the existing destinations | **PASS** |
| Recent Activity referral links opened the existing destinations | **PASS** |
| Package links opened the existing destinations | **PASS** |
| LA Decision links opened the existing destinations | **PASS** |
| Assessment links opened the existing destinations | **PASS** |
| Links unchanged | **PASS** |
| Dashboard counts and data unchanged | **PASS** |
| Dashboard GET created no activity | **PASS** |
| Dashboard GET caused no workflow mutation | **PASS** |
| No referral record changed | **PASS** |
| No assessment record changed | **PASS** |
| No package-cost record changed | **PASS** |
| No LA-decision record changed | **PASS** |
| No meeting record changed | **PASS** |
| No occupancy or commencement record changed | **PASS** |
| No privacy regression | **PASS** |

---

## Focused visual checklist (summary)

| # | Check | Result |
| --- | --- | --- |
| 1 | Plugin activation smoke | **PASS** |
| 2 | Versions unchanged | **PASS** |
| 3 | Management Dashboard opens | **PASS** |
| 4 | Operations tab opens | **PASS** |
| 5–7 | Workflow padding / alignment / zero stages | **PASS** |
| 8–9 | Assessments KPI / outcomes | **PASS** |
| 10–13 | Package Costing layout / table | **PASS** |
| 14 | LA Decision KPI layout | **PASS** |
| 15–16 | Recent Referrals / status badges | **PASS** |
| 17–18 | Recent Activity / natural card heights | **PASS** |
| 19–23 | 1440 / 1280 / 1024 / 768 / 375 | **PASS** |
| 24–25 | Overflow (page / table-contained) | **PASS** |
| 26–28 | Keyboard / headings / colour-independent meaning | **PASS** |
| 29 | Print smoke | **PASS** |
| 30–33 | Counts / routes / privacy / workflow | **PASS** |

---

## Sign-off

| Role | Name | Date | Result |
| --- | --- | --- | --- |
| Tester | Manual visual UAT | 2026-08-27 | **PASS** |
| Reviewer | | | |

**Verdict:** **PASS** (2026-08-27)

**Confirmed:** Visual UAT passed; sections polished; shared dashboard spacing system introduced; workflow stages remain fully visible; responsive KPI layouts verified; tables verified at all target widths; status badges contain text; activity hierarchy improved; natural card heights verified; keyboard focus verified; print preview verified; no count/data/query changes; no privacy regression; no workflow mutation; versions unchanged (Product **1.4.0** · DB **2.29.0** · rewrite **1.2.7**).
