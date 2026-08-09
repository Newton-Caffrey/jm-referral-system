# UI Style Guide — JM Referral System

**Phase:** 5.5  
**Applies to:** WordPress admin screens for this plugin (body class `jmrs-admin`)  
**Assets:** `assets/css/admin.css`, `assets/js/admin.js`, helper `JMReferral\Support\UiHelper`

Do not invent parallel styles in templates. Prefer shared classes and `UiHelper`.

---

## Typography

| Element | Guidance |
| --- | --- |
| Page title | Core admin `<h1>` (optionally with badge) |
| Section | `<h2>` for major modules on View |
| Subsection | `<h3>` for visit execution / nested blocks |
| Body | WordPress admin default |
| Help text | `.description` or `.jmrs-help` |

Avoid custom webfonts in admin.

---

## Spacing

| Token | Approx. use |
| --- | --- |
| 4–8px | Badge padding, tight gaps |
| 8–12px | Button/filter gaps, table cell comfort |
| 12–16px | Form row padding, card padding |
| 20–36px | Section separation (reports slightly roomier) |

Use flex wrappers `.jmrs-page-actions` / `.jmrs-quick-actions` instead of ad-hoc margins on every button.

---

## Colours

Aligned with WordPress admin:

| Role | Typical use |
| --- | --- |
| `#1d2327` / `#2c3338` | Primary text |
| `#646970` / `#50575e` | Secondary text, help |
| `#2271b1` | Focus ring, primary actions |
| `#b32d2e` | Danger / errors |
| `#007017` | Success chips |
| `#8a6116` / `#996800` | Warning / high priority |
| `#646970` on white text | Archived badge |
| `#fff` + `#c3c4c7` border | Cards / filters |

---

## Buttons

| Type | Class / pattern | Examples |
| --- | --- | --- |
| Primary | `button button-primary` | Save, Create, Edit, Restore, Complete Visit |
| Secondary | `button` / `button-secondary` | Back, Reset Filters, Generate (non-destructive) |
| Danger | `button jmrs-button-danger` | Archive, Delete |
| Small | `button … small` | Inline visit actions |

**Busy labels** (via `admin.js` / `data-jmrs-busy`):

- Saving…
- Generating…
- Uploading…
- Completing Visit…
- Reviewing…
- Archiving…
- Restoring…
- Exporting…

---

## Badges

Render with `UiHelper::badge()`, `priority_badge()`, `status_badge()`, or `alert_badge()`.

| Variant | Use |
| --- | --- |
| `priority-low\|medium\|high\|urgent` | Referral priority |
| `success` / `info` / `warning` / `danger` | Status families |
| `archive` | Archived referral |
| `alert-critical\|warning\|information` | Operational alerts |

Always include readable text inside the chip.

---

## Tables

- Class: `wp-list-table widefat fixed striped table-view-list`
- Headers: `<th scope="col">`
- Empty rows: `.no-items` + `UiHelper::empty_state()`
- Row actions: `.jmrs-actions` (flex wrap, no brittle `\|` spacing dependence)
- Hover: light grey background (shared CSS)
- Pagination: `.tablenav` + `.displaying-num` (“Displaying X–Y of Z …”)

---

## Alerts / notices

Use WordPress notice types only:

| Class | Meaning |
| --- | --- |
| `notice notice-success` | Completed action |
| `notice notice-warning` | Caution / partial success |
| `notice notice-error` | Failure / validation |
| `notice notice-info` | Neutral information |

**Wording pattern:** `{Noun} {past participle} successfully.`  
Examples: `Referral created successfully.` · `Medication updated successfully.` · `Visit completed successfully.`

Validation intro: `Please fix the following errors:`

---

## Forms

- Wrapper: `table.form-table` with `role="presentation"`
- Labels: `<label for="…">` matching control `id`
- Required: add `jmrs-required` on the label + HTML `required` where appropriate
- Errors: field-level `.description` under the control; optional grouped `.jmrs-field-errors`
- Confirm destructive submits: `data-jmrs-confirm="…"` on `<form>` or submit control

---

## Empty states

```php
echo \JMReferral\Support\UiHelper::empty_state(
    __( 'No referrals found.', 'jm-referral-system' ),
    '<a class="button" href="…">' . esc_html__( 'Add New Referral', 'jm-referral-system' ) . '</a>'
);
```

Message first; optional single helpful action.

---

## Confirmations

| Action | Suggested copy |
| --- | --- |
| Archive | Archive this referral? Clinical records will be preserved but the referral will become read-only. |
| Restore | Restore this archived referral? |
| Delete | Permanently delete this empty referral? This cannot be undone. / Delete this {entity}? This cannot be undone. |
| Generate visits | Generate visits for this schedule and date range? |
| Complete visit | Complete this visit? Recorded tasks and medication administrations will be saved. |
| Review visit | Mark this visit as reviewed? |

Implement with `data-jmrs-confirm` (handled by `admin.js`), not mixed `onclick="confirm(...)"`.

---

## Icons

- Prefer **text labels** for plugin actions.
- Do not introduce a second icon font.
- If an icon is needed, use a single WordPress **dashicon** consistently and keep an accessible text label.

---

## Mobile

Breakpoints in `admin.css`:

- **≤1024px** — form-table stacks; tables scroll horizontally  
- **≤782px** — action bars stack; tablenav stacks  
- **≤480px** — stats single column  

Test Referral List, Referral View, Reports, and Alerts at these widths.

---

## Reports

Logic unchanged for date-range sections. Spacing via shared admin CSS (section margins, KPI gaps, filter padding). Charts remain Chart.js + accompanying tables.

Supported Living — Current Snapshot uses `.jmrs-report-section--snapshot` and the same KPI/table/chart patterns; multi-column occupancy table scrolls horizontally inside `.jmrs-report-table-wrap` on narrow viewports.

Vacancy Report uses the same snapshot section styling; vacancy table cells wrap (`word-break` / `overflow-wrap`) to avoid horizontal page overflow.
