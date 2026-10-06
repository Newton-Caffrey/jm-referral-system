# UAT — Phase 5E.1 Referral form upload

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.10  
**Previous checkpoint:** `39b9434` (Merge Phase 5D referral inbox workflow)  
**Branch:** `develop/1.6.0` (changes uncommitted at hand-over)

**Overall result:** **DEVELOPMENT VERIFICATION ONLY — STAGING UAT NOT RUN**

**Verification date:** 2026-10-06

**Method:** A throwaway WordPress 6.8 site on PHP 8.3 was driven over HTTP as Care Coordinator, Referral Manager, Assessor, Support Worker, administrator, and signed out. Forms were made-up Word and PDF files in several layouts. No real referral form and no real personal data was used.

**Environment limits. Read before relying on this record.**

- The test site used SQLite, not MySQL. SQLite has no named locks or row locks, so `GET_LOCK`, `RELEASE_LOCK`, and `FOR UPDATE` were stood in for by a test-site shim. The conversion transaction, the referral-number lock, and rollback were **not** exercised on InnoDB here. Phase 5D.5 UAT covers them; this phase does not change that code path before commit.
- PHP's built-in web server ignores `.htaccess`, so private-directory protection was not tested.
- Email delivery was not available.
- Browser checks were screenshots at desktop and phone width only. No assistive-technology pass.

Staging UAT on the real stack is required before this phase is treated as accepted.

---

## Automated checks run

| Suite | Checks | Result |
| --- | --- | --- |
| Field extraction over 13 made-up forms, custom labels, bad label input | 135 | **PASS** |
| End-to-end through the Staff Portal and wp-admin Settings | 81 | **PASS** |

## Access

| Check | Result |
| --- | --- |
| Care Coordinator and Referral Manager can open the upload screen | **PASS** |
| Assessor and Support Worker receive 403 on GET and POST | **PASS** |
| Signed-out visitor is sent to login | **PASS** |
| Refused requests create no Inbox item | **PASS** |
| Care Coordinator cannot save label settings | **PASS** |

## Rejected uploads

| Check | Result |
| --- | --- |
| No file, empty file, 11 MB file | **PASS** |
| Text file renamed `.pdf`; PHP file renamed `.docx` | **PASS** |
| `.doc`, image | **PASS** |
| Wrong security token | **PASS** |
| None of these created an Inbox item or stored a file | **PASS** |

## Upload to referral

| Check | Result |
| --- | --- |
| Upload redirects to Prepare Referral | **PASS** |
| Inbox item is `manual`, `needs_review`, reviewed by the uploader, reason `staff_uploaded_referral_form` | **PASS** |
| Inbox row holds no form content | **PASS** |
| File stored privately with checksum | **PASS** |
| Thirteen fields and care requirements prefilled from a table-layout Word form | **PASS** |
| Service type, referral source, priority start unselected; hints shown as suggestions | **PASS** |
| Missing required selects, future date of birth, malformed date of birth refused | **PASS** |
| Validate creates nothing; Create without the tick is refused | **PASS** |
| Create Referral: one referral carrying every reviewed field | **PASS** |
| Inbox item accepted and linked; attachment `promoted` | **PASS** |
| Form attached to the referral as a private document; one file on disk | **PASS** |
| Activity `created`, `document_uploaded` | **PASS** |
| Second Create Referral submit makes no second referral or document | **PASS** |
| Form downloads from the referral, byte-identical; refused when signed out | **PASS** |

## Duplicates

| Check | Result |
| --- | --- |
| Same file again: existing item, no new file | **PASS** |
| Same content under another file name is recognised | **PASS** |
| An ignored form can be uploaded afresh; a third upload creates nothing | **PASS** |

## Reading

| Check | Result |
| --- | --- |
| Label-and-value lines, tables, header-row tables, tab-aligned lines, label-above-value | **PASS** |
| Plain labels assigned by section; next of kin and GP details not taken as client or referrer | **PASS** |
| Two different client names: blank, both listed | **PASS** |
| Fillable PDF and typed PDF | **PASS** |
| Scanned PDF: stored, nothing prefilled, reason shown | **PASS** |
| Unrelated document and blank template: nothing prefilled | **PASS** |
| Message and form disagree: blank, both listed | **PASS** |
| Stored file missing: referral still created, warning shown, attachment stays `stored` | **PASS** |

## Unchanged behaviour

| Check | Result |
| --- | --- |
| Email-style Inbox item prefills from the message as before and converts with no warning | **PASS** |
| Inbox list and detail render | **PASS** |
| No PHP errors or database errors from the plugin in the debug log | **PASS** |

## Settings

| Check | Result |
| --- | --- |
| Label lists shown as built-in; save, markup refusal, reset | **PASS** |
| A stored form is read with newly added labels on the next open | **PASS** |

---

## Not run

| Item | Status |
| --- | --- |
| MySQL / InnoDB transaction, number lock, rollback with the new post-commit step | **NOT RUN** |
| Upgrade of an existing site (rewrite flush `1.2.9` → `1.2.10`) | **NOT RUN — CODE REVIEWED** |
| A real care-provider referral form | **NOT RUN — no sample available** |
| Private-directory web protection | **NOT RUN** |
| Assignment email after conversion from an uploaded form | **NOT RUN** |
| Two simultaneous uploads of the same file | **NOT RUN — CODE REVIEWED** |
| PHP 8.0 runtime (verified on 8.3 only) | **NOT RUN** |
| Screen reader and keyboard-only pass | **NOT RUN** |
| `languages/jm-referral-system.pot` regeneration for the new strings | **NOT DONE** |

## Existing defects found and fixed alongside this phase

Both were in unchanged code and are also present in the 1.5.0 package. They were seen on the test site only; confirm on staging.

| Defect | Fix | Result on test site |
| --- | --- | --- |
| Staff Portal referral view was cut off at the pipeline panel for users who can override the pipeline stage (Referral Manager, administrator). `templates/referrals/partials/pipeline-panel.php` called `submit_button()`, which exists only in wp-admin | The panel renders a portal button when `$context` is `portal`, as the other shared panels do. wp-admin output is unchanged | Page renders to the end for Referral Manager, administrator, Care Coordinator; override submits from the portal and reports success; wp-admin view still renders — **PASS** |
| Referral Inbox detail and Prepare Referral showed Subject and Sender as “—” and no message preview. `templates/portal/layout.php` left its navigation loop variable `$item` set, and the view model is extracted with `EXTR_SKIP` | The layout unsets its navigation loop variables before extracting the view model. `item` was the only view key affected | Subject, sender and preview shown on both pages for uploaded and email-style items; dashboard, referrals, management, homes, occupancy and Inbox pages still render — **PASS** |

The 81 end-to-end checks were re-run after these fixes: **PASS**.
