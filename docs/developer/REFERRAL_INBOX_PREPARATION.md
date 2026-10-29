# Referral Inbox preparation (Phase 5D.4)

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.9

Staff with Inbox management and `CREATE_REFERRALS` can review advisory candidate fields and check a draft before any referral exists.

The screen is `/referral-inbox/{id}/prepare/` (`referral_inbox_prepare`). `PortalUrls::referral_inbox_prepare()` builds the link. Opening it does not change the Inbox row.

## Access and lifecycle

`AccessPolicy::can_prepare_referral_from_inbox()` requires `can_manage_referral_inbox()` and `CREATE_REFERRALS`. Assessor and Support Worker are denied. Direct URLs use the same rule.

The editable form is shown only while status is `needs_review`.

| Status | Screen |
| --- | --- |
| `new` | “Start Review before preparing this opportunity as a referral.” |
| `accepted`, `ignored`, `duplicate`, `error` | A state-specific message and a link back to the Inbox item |
| `needs_review` | The preparation form |

A validation POST that finds any other status is rejected with “This opportunity has already changed. Refresh the page to see its current status.” The terminal status is left as it is.

The Inbox detail page shows **Prepare Referral** only for `needs_review` and only for a user who can prepare. It is a GET link. It is not labelled Accept Referral.

## Prefill

`ReferralInboxPreparationService` calls `ReferralInboxCandidateExtractor` and copies a value into the form only when that candidate state is `single`.

| State | Form |
| --- | --- |
| `single` | Prefill that value |
| `none` | Leave the field blank |
| `ambiguous` | Leave the field blank, show a warning, and list the alternatives for authorised staff |

Ambiguous client names use “Multiple possible client names were found. Please enter the correct value.” Other ambiguous fields use “Multiple possible values were found. Please confirm manually.”

Referrer organisation follows the stored Local Authority decision:

| Origin | Organisation field |
| --- | --- |
| `confirmed` | Prefill, note “From staff-confirmed Local Authority” |
| `suggested` | Prefill, note “From JMRS Local Authority suggestion” |
| stored id with no origin | Prefill, note “From linked Local Authority” |
| `cleared` or no id | Blank. Sender matching is not run again |

Client fields that were prefilled are labelled “Suggested from referral message”. Referrer name and email are labelled “Suggested from sender”.

## Human choices

Service type, referral source, and priority are required and start unselected.

- Service hint text such as “JMRS suggestion: Supported Living” is advisory. It does not set `service_type_id`. The select lists active service types from `ServiceTypeService::get_active()` and starts at “Select service type”.
- Referral source uses `ReferralSources::options()` and starts at “Select referral source”.
- Priority hint text such as “JMRS suggestion: Urgent” is advisory. The select starts at “Select priority”. A missing or invalid priority fails validation. The admin default of medium is not applied here.

Assignment is optional and defaults to Unassigned. The field is shown only when the current user has `ASSIGN_REFERRALS`. A posted `assigned_to` is ignored for everyone else. Notes start blank. The email body and attachment names are not copied into notes.

## Validation

**Validate Details** posts back to the same route. The handler checks the current user, the prepare capability, a nonce, and the route id. Inbox status is read again from storage.

`ReferralValidator` checks client name, an active selectable service type, referral source, optional emails, and an assignable user when one is chosen. The preparation payload omits workflow stage, status, and care setting so those referral-edit rules do not apply. Priority is checked in the preparation service.

When the draft passes, the screen says:

- “Referral details are valid and ready for confirmation.”
- “No referral has been created yet.”

If another field fails, the response keeps the values the staff member submitted. Extractor suggestions do not replace them. Nothing is stored in the database, `wp_options`, a transient, a session, or a cookie.

## Uploaded referral forms (Phase 5E.1)

When the Inbox item holds an uploaded referral form, suggestions read from that file are merged into this screen under the same rules: one clear value prefills, an ambiguous one is listed and left blank. The form also gains optional fields for date of birth, address, referrer phone, relationship, care start date, and care requirements. See [`REFERRAL_INBOX_DOCUMENT_UPLOAD.md`](REFERRAL_INBOX_DOCUMENT_UPLOAD.md).

## Boundaries

This phase does not call `ReferralService::create()` or `ReferralInboxService::markAccepted()`. It does not generate a referral number, write referral activity, send a notification, change detection, or confirm or clear a Local Authority. Authority corrections stay on the Inbox detail screen via **Review Local Authority**.

`submission_channel` is unchanged because Validate Details does not create a referral.

Phase **5D.5** adds **Create Referral** on this same route after the staff member confirms the validated details. That action is documented in [`REFERRAL_INBOX_CONVERSION.md`](REFERRAL_INBOX_CONVERSION.md). An accepted and linked item no longer shows the editable form.
