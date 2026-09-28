# Referral Inbox authority review (Phase 5D.2)

**Product:** 1.5.0  
**Database:** 2.33.0  
**Portal rewrite:** 1.2.8 (no new route)

Staff can see why an Inbox item was classified, see the current sender-recognition result, and explicitly confirm or clear a Local Authority. Detection stays advisory. This phase does not create referrals.

## Provenance

`jmrs_referral_inbox` columns:

| Column | Meaning |
| --- | --- |
| `local_authority_origin` | `suggested`, `confirmed`, `cleared`, or `NULL` |
| `local_authority_decided_by` | WordPress user id for a human decision |
| `local_authority_decided_at` | MySQL time of that human decision |

| Origin | Meaning |
| --- | --- |
| `suggested` | JMRS linked the authority from a recognised sender rule. No human actor or time is stored. |
| `confirmed` | An authorised staff member selected or confirmed an active authority. |
| `cleared` | An authorised staff member decided that no Local Authority should currently be linked. |
| `NULL` | Legacy, unspecified, or no decision recorded. |

Existing rows with a non-null `local_authority_id` and a `NULL` origin stay valid. They are displayed as “Linked authority — source not recorded” and are not backfilled.

## Automatic suggestion

`set_local_authority_if_null()` writes only when both `local_authority_id` and `local_authority_origin` are `NULL`. On success it sets the matched id, origin `suggested`, and null decision actor/time.

It does not replace:

- a non-null authority id
- origin `confirmed`
- origin `cleared` (the id stays null, so a later match cannot silently return)

## Human decisions

`ReferralInboxService::confirmLocalAuthority()` and `clearLocalAuthority()` are the only staff write paths. Templates do not call repositories.

Both require a real current WordPress user and lifecycle `status = needs_review`. The repository `UPDATE` repeats `AND status = 'needs_review'`. If the row changed, the service returns `INVALID_STATE` or `CONFLICT`. The portal says: “This opportunity has already changed. Refresh the page to see its current status.”

Confirm also requires an active Local Authority. It may keep the suggested authority or choose a different active one. An inactive authority cannot be newly confirmed. If a previously linked authority is now inactive, the detail page still shows it and asks for a different active authority.

Clear sets `local_authority_id` to `NULL` and origin to `cleared`. It does not reset origin to `NULL`.

Neither action changes `detection_status` or `detection_reason`.

Results: `SUCCESS`, `NOT_FOUND`, `INVALID_AUTHORITY`, `INVALID_ACTOR`, `INVALID_STATE`, `CONFLICT`, `PERSISTENCE_ERROR`. SQL and exception text are not shown.

## Detail page

GET is read-only. It may evaluate `LocalAuthoritySenderMatcher::matchSender()`, build the explanation, load active authorities, and resolve display names. It does not change detection, authority, lifecycle, or review state.

**Detection** maps the stored reason code to a staff sentence. The raw code is a small technical line. Explanations do not include message excerpts.

**Sender recognition** is the current rule evaluation:

- Recognised sender, with the matched authority and Exact email rule or Domain rule
- No configured Local Authority sender rule matches this address
- Multiple Local Authorities match this sender, with candidate names only
- Sender could not be evaluated

Recognised sender means the address matches a configured sender rule. It does not verify or authenticate the email sender. A no-match is not the same fact as “not a referral”. Ambiguous candidates are not stored and one is not chosen automatically.

**Local Authority** is the stored decision: Suggested by JMRS, Confirmed by staff, Cleared by staff, Linked authority — source not recorded, or No Local Authority linked. Confirmed and cleared show the decision display name (or “Former user / User #ID”) and time.

`new`: explanation and recognition are visible. Confirm and clear are not. The page says to start review first.

`needs_review` plus `can_manage_referral_inbox()`: Confirm Local Authority and Clear Local Authority POSTs, with the existing Inbox nonce. The actor is the current user.

`accepted`, `ignored`, `duplicate`, and `error`: the authority section is read-only. There is no reopen action and no production Re-run Detection button.

View access remains `can_view_referral_inbox()`. Assessor and Support Worker stay denied.

## Boundaries

No `ReferralService::create()`. No Accept, Prepare, or Create Referral from Inbox. No Graph, OAuth, webhook, delta sync, mailbox polling, or external classifier.
