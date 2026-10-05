# Referral Inbox conversion (Phase 5D.5)

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.9 (unchanged — no new route)

Create Referral posts to the existing preparation route `/referral-inbox/{id}/prepare/`.

## Human confirmation

Validate Details still only checks the draft. When that check passes, the screen keeps the two confirmation sentences and shows:

- a checkbox, “I have reviewed these details and want to create the referral.”
- **Create Referral**
- the warning “Creating the referral will accept this Inbox opportunity and link the two records.”

The server does not trust that earlier validation. The Create Referral POST checks the current user, `can_prepare_referral_from_inbox()`, the nonce, the route id, the checkbox, and the submitted fields again. A missing checkbox does not create a referral. Posted status, actor, linked referral, detection, authority origin, and submission channel are ignored.

## Transaction

`ReferralInboxConversionService` refuses to start when any critical write table is not InnoDB:

- referrals
- referral activity
- referral stage history
- referral inbox

After the preliminary field check, the service:

1. `START TRANSACTION`
2. `SELECT … FOR UPDATE` on the Inbox row
3. acquires the referral-number advisory lock `jmrs_referral_number` (5 second timeout)
4. revalidates the sanitized payload
5. calls `ReferralService::create_database_effects()`
6. calls `ReferralInboxService::markAccepted()` with `get_current_user_id()`
7. `COMMIT` only when both steps succeed
8. releases the number lock
9. calls `dispatch_created_notification()`

Any failure before commit rolls the transaction back and releases the lock. There is no compensating delete. Email is not attempted.

A second request waits on the row lock. After the first commit it sees `accepted` and `linked_referral_id` and returns the existing referral. The browser is redirected to the Inbox detail so a refresh does not submit the form again.

`needs_review` with a link already set, and `accepted` with no link, are refused and are not repaired.

## Referral number lock

`ReferralNumberGenerator` still uses `JM-YYYYMMDD-####` from a count plus one. There is still no unique key. `ReferralService::create()` and Inbox conversion both hold `GET_LOCK('jmrs_referral_number', 5)` while the number is chosen. Conversion keeps that lock until commit or rollback, because another connection cannot see an uncommitted referral. If the lock is not acquired, creation stops.

Normal admin and public creates still return the same `array|false` contract. They release the lock after the database insert is visible, then send the same assignment email as before.

## What is stored

The referral is status `new`. `submission_channel` is `admin`, because the normaliser only preserves `public_website` and maps every other staff value to `admin`. The Inbox row remains the conversion record through `linked_referral_id`, `accepted_by`, and `accepted_at`.

The referral table has no `local_authority_id`. The reviewed referrer organisation text is copied. The authority id and provenance stay on the Inbox item.

Activity is the ordinary `created` row, plus `pipeline_started` and `assigned` when those already apply to a normal create. There is no separate conversion activity action.

If the assignment email fails after commit, the referral and the accepted Inbox link remain. The Inbox detail shows a non-blocking warning. Another attempt does not create a second referral.

## Access

Create Referral uses the same prepare capability: Inbox management and `CREATE_REFERRALS`. Users without `ASSIGN_REFERRALS` cannot set `assigned_to`.
