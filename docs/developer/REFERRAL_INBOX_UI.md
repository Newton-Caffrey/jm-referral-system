# Referral Inbox UI (Phase 5B.3)

**Product:** 1.5.0  
**Database:** **2.31.0** (unchanged — no migration)  
**Portal rewrite:** **1.2.8** (new Staff Portal routes)  
**Branch:** `develop/1.6.0`

Staff Portal UI for reviewing Referral Inbox opportunities **before** they enter the referral workflow.

**Still out of scope:** Microsoft Graph / Outlook / Gmail / OAuth / webhooks / mailbox sync / tokens; automatic detection/classification; sender matching during ingestion; Accept → `ReferralService::create()`; attachment file download/storage; Expression of Interest; dashboard metrics; cron.

---

## Routes

| Route | Path | Purpose |
| --- | --- | --- |
| `referral_inbox` | `/referral-inbox/` | List + filters |
| `referral_inbox_item` | `/referral-inbox/{id}/` | Detail + POST actions |

Rewrite version bumped **1.2.7 → 1.2.8** because new rewrite rules were added (`PortalRouter::REWRITE_VERSION`). WordPress flushes when the stored option lags.

URLs: `PortalUrls::referral_inbox()`, `referral_inbox_with_args()`, `referral_inbox_item()`.

---

## Access policy

| Method | Rule |
| --- | --- |
| `AccessPolicy::can_view_referral_inbox()` | `VIEW_REFERRALS` + commercial/management roles (Platform Admin / WP admin, JM Administrator, Referral Manager, Care Coordinator) |
| `AccessPolicy::can_manage_referral_inbox()` | View access + `EDIT_REFERRALS` |

Denied: Assessor, Support Worker.  
No new capability invented. Navigation visibility and direct URL/POST use the same policy.

Linked accepted referrals use existing `can_view_referral()` before showing **View Referral**.

---

## List

- Header: **Referral Inbox**
- Lifecycle tabs with repository `countByStatus()`: All, New, Needs Review, Accepted, Ignored, Duplicates, Errors
- Search (prepared SQL + `esc_like`): subject, sender_name, sender_email, internet_message_id, provider_message_id
- Pagination: 20 per page; order `received_at DESC, id DESC`
- Columns: Received, Sender, Subject, Local Authority, Detection, Status, Attachments, Reviewed By, Actions
- Empty state does **not** say “Connect Outlook”
- Fixture source labelled **Test Fixture**
- GET is non-mutating

---

## Detail

Sections: Overview, Message (escaped plaintext body preview with `white-space: pre-wrap`), Identifiers, Attachments (metadata only — no download), Review, Outcome, Actions.

GET never sets `reviewed_at` / status.

---

## Actions (POST + nonce + PRG)

| Action | From status | Service calls |
| --- | --- | --- |
| Start Review | `new` | `markNeedsReview()` then `markReviewed(current user)` |
| Ignore Opportunity | `needs_review` | `markIgnored()` — requires confirm checkbox |
| Mark as Duplicate | `new` / `needs_review` | `markDuplicate(target_id)` — Inbox ID field + optional preview |
| Return to Review | `error` | `markNeedsReview()` then `markReviewed()` when appropriate |

**No Accept Referral button** in 5B.3.

Terminal states (`accepted`, `ignored`, `duplicate`) are read-only (no reopen).

Stale/invalid transitions surface:

> This opportunity has already changed. Refresh the page to see its current status.

Actor ID always comes from the authenticated user — request parameters cannot override actor, status, LA, or linked referral.

---

## Navigation badge

Actionable count badge (`new` + `needs_review`) **deferred** — would require an extra query on every portal chrome render; not worth the cost without a shared cache/context.

---

## Components

| Class / asset | Role |
| --- | --- |
| `Portal\ReferralInbox\InboxHandler` | Routes, list/detail, POST actions |
| `ReferralInboxRepository::query/count/countByStatus` | Read models |
| `LocalAuthorityRepository::findNamesByIds` | Batch LA names |
| `templates/portal/referral-inbox/*` | List + detail |
| `assets/css/portal.css` | Tabs, badges, body preview, responsive |

Mutations never go through templates or raw SQL in views.

---

## Related docs

- [`REFERRAL_INBOX_DATA_MODEL.md`](REFERRAL_INBOX_DATA_MODEL.md)
- [`REFERRAL_INBOX_SERVICE.md`](REFERRAL_INBOX_SERVICE.md)
- [`../uat/UAT_PHASE_5B_3_REFERRAL_INBOX_UI.md`](../uat/UAT_PHASE_5B_3_REFERRAL_INBOX_UI.md)
