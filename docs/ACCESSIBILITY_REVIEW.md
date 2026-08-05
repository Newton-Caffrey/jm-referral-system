# Accessibility Review — JM Referral System

**Phase:** 5.5  
**Date:** 2026-08-05  
**Scope:** WordPress admin UI for the JM Referral System plugin (screens under `jm-referrals*`)

This review covers keyboard use, semantics, notices, tables, forms, and charts after Phase 5.5 UX polish. It is not a WCAG audit certificate.

---

## Findings addressed in 5.5

| Area | Improvement |
| --- | --- |
| Focus | Visible `:focus` outline on links, buttons, and form controls within `.jmrs-admin` |
| Labels | Filter controls use `screen-reader-text` / associated `for`/`id` pairs on list and alerts |
| Required fields | `.jmrs-required` marker (asterisk) for required labels; HTML `required` retained |
| Badges | Status/priority/severity use text labels inside chips (colour is not the only cue) |
| Tables | Column headers use `<th scope="col">`; empty states use `role="status"` |
| Confirmations | Destructive actions use explicit confirm copy via `data-jmrs-confirm` |
| Busy state | Submit controls set `aria-busy="true"` and disable duplicates while submitting |
| Body class | `jmrs-admin` on plugin screens for scoped styles |

---

## Keyboard support

| Interaction | Support |
| --- | --- |
| Tab through primary actions, filters, table links | Yes (native admin + plugin controls) |
| Activate links / buttons with Enter / Space | Yes |
| Native `<select>` / date / time inputs | Browser defaults |
| Pagination links | Standard WordPress `paginate_links` anchors |
| Modal dialogs | Not used — browser `confirm()` only |
| Skip link | Relies on WordPress admin chrome |

**Limitation:** Nested visit execution forms on Referral View are long; keyboard users must tab through many fields. Consider a future “jump to visit” control if visit density grows.

---

## Screen reader notes

- Page titles use `<h1>`; sections use `<h2>` / `<h3>` in templates (generally sequential).
- Notices use WordPress `.notice` markup (`role` provided by WP core patterns).
- Badge text is readable (not icon-only).
- Charts on Reports: Chart.js canvas is visual; data tables accompany datasets so information is not chart-only.
- Empty states announce via `role="status"`.

**Limitation:** Some complex visit execution blocks still rely on visual layout more than landmark regions. ARIA `region` / `aria-labelledby` could be added later without changing workflows.

---

## Colour and contrast

- Badge palettes follow WordPress admin greys/blues/greens/ambers/reds with text labels.
- Danger buttons use red border/text and the word “Archive” / “Delete”.
- Alert severity is both colour-coded and labelled (Critical / Warning / Information).

**Remaining risk:** Custom inline styles on older dashboard/alerts snippets may diverge slightly from shared `admin.css` until fully migrated.

---

## Remaining limitations

1. **Browser `confirm()`** — not ideal for screen readers or mobile; a future accessible modal would improve UX.
2. **Charts** — canvas is not fully accessible; tables mitigate this.
3. **Wide tables** — horizontal scroll on small screens; sticky first columns not implemented.
4. **Iconography** — plugin mostly uses text buttons; WordPress dashicons appear only where core UI provides them (not mixed custom icon fonts).
5. **Automated axe/WAVE scans** — not run in CI; recommend periodic manual checks on View, List, Reports, Alerts.

---

## Future recommendations

1. Replace `confirm()` with an accessible dialog component (focus trap, Esc to cancel).
2. Add `aria-live` polite region for AJAX-free flash notices if notices move inline.
3. Landmark regions for Referral View modules (Visits, MAR, Care Plan).
4. Sticky table first column on mobile for referral list.
5. Prefer reduced-motion for any future animations (`prefers-reduced-motion`).
6. Run axe DevTools on staging after each UI-heavy release.

---

## Phase 5.6 confirmation

No accessibility regressions introduced in the release-readiness pass. Remaining limits match `docs/KNOWN_LIMITATIONS.md` (browser `confirm()`, chart canvas, dense Referral View).
