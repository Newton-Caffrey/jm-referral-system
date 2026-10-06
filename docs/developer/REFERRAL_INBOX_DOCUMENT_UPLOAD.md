# Referral Inbox document upload (Phase 5E.1)

**Product:** 1.5.0 (unchanged)  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** **1.2.10** (new route `referral_inbox_upload`)  
**Branch:** `develop/1.6.0`

Staff upload a completed referral form as a Word (`.docx`) or PDF file. JMRS reads the details out of the file and offers them on the existing Prepare Referral screen. The referral is still created only after a member of staff has reviewed the fields, validated them, and ticked the confirmation.

This is the first intake source that puts real items into the Referral Inbox. It uses the `manual` source that Phase 5B.1 reserved.

---

## Flow

```text
/referral-inbox/upload/            UploadHandler
  → ReferralInboxDocumentService::upload()
      validate file → SHA-256 → InboundMessage (manual) → ReferralInboxIngestionService::ingest()
      → move file to uploads/jmrs-private/ → ReferralInboxService::addStoredAttachment()
      → markNeedsReview() + markReviewed(uploader)
  → redirect to /referral-inbox/{id}/prepare/

/referral-inbox/{id}/prepare/      PrepareHandler (unchanged route)
  → ReferralInboxPreparationService
      → ReferralInboxCandidateExtractor            (message metadata, as before)
      → ReferralInboxDocumentService::candidates() (reads the stored file, in memory)
  → Validate Details → confirm → Create Referral
  → ReferralInboxConversionService::commit()       (same transaction as before)
      → after COMMIT: ReferralInboxDocumentService::promote_to_referral()
      → after COMMIT: dispatch_created_notification()
```

Nothing new is trusted. The Create Referral POST still re-checks the user, the capability, the nonce, the route id, the checkbox, and every submitted field.

---

## Components

| Class | Role |
| --- | --- |
| `Portal\ReferralInbox\UploadHandler` | Upload screen. GET renders the form; POST stores one file and redirects |
| `ReferralInbox\Document\ReferralInboxDocumentService` | Validates and stores the file, creates the Inbox item, reads suggestions, attaches the file to the referral |
| `ReferralInbox\Document\DocumentTextReader` | Picks a reader by extension |
| `ReferralInbox\Document\DocxTextReader` | Reads `word/document.xml` from the `.docx` zip |
| `ReferralInbox\Document\PdfTextReader` | Reads the PDF text layer and fillable-field values with the bundled library |
| `ReferralInbox\Document\ExtractedDocument` | In-memory rows, cells, and form fields |
| `ReferralInbox\Document\ReferralFormFieldExtractor` | Turns that text into advisory field suggestions |
| `ReferralInbox\Document\ReferralFormExtractionResult` | In-memory result: one `ReferralInboxCandidateField` per field |
| `ReferralInbox\Document\ReferralFormLabels` | Label wording, editable in wp-admin Settings |

Changed, with behaviour for email-style items left as it was:

| Class | Change |
| --- | --- |
| `ReferralInboxService` | `addStoredAttachment()`, `markAttachmentPromoted()` |
| `ReferralInboxAttachmentRepository` | `transition_storage_status()` |
| `ReferralInboxPreparationService` | Merges form suggestions; nine more form fields |
| `ReferralInboxConversionService` | Passes the new fields to `create_database_effects()`; attaches the file after commit |
| `ReferralInboxConversionResult` | `WARNING_DOCUMENT_ATTACH`, `has_warning()` |
| `ReferralDocumentService` | `attach_private_file()` |
| `ReferralInboxDetectionResult` / `…Explanation` | `REASON_STAFF_UPLOADED_FORM` |
| `InboxHandler`, `PrepareHandler`, `PortalRouter`, `PortalUrls`, `PortalNavigation` | Route, link, button, posted-field allowlist |
| `Admin\Pages\SettingsPage` | “Referral Form Upload: Field Labels” section |

`ReferralInboxCandidateExtractor` is not changed. It still reads message metadata only and still never opens a file.

---

## Access

`AccessPolicy::can_prepare_referral_from_inbox()`: Inbox management plus `CREATE_REFERRALS`. This is the same rule as Prepare Referral. No capability was added. Assessor and Support Worker are denied on GET and POST. The Upload Referral Form button is shown on the Inbox list only to users who pass that check.

---

## File handling

| Check | Rule |
| --- | --- |
| Types | `.docx` and `.pdf` only. `.doc` is refused with a message asking for `.docx` or PDF |
| Size | 10 MB, the same limit as referral documents |
| Type verification | Extension, `wp_check_filetype_and_ext()` against the two allowed MIME types, and the file's leading bytes (`%PDF-`, or `PK\x03\x04`) |
| Upload origin | `is_uploaded_file()` and `move_uploaded_file()` |
| Storage | `uploads/jmrs-private/YYYY/MM/<random>.<ext>` through `PrivateDocumentStorage`. Never the Media Library |
| One file | A multi-file POST is refused |
| CSRF | Nonce `jmrs_inbox_upload_form` |

The private directory is protected the same way as every other referral document. The existing note in Settings applies: the `.htaccess` deny rule works on Apache-compatible hosts and may not apply on some nginx setups.

### Reading `.docx`

`DocxTextReader` opens the zip read-only and reads `word/document.xml` only, capped at 15 MB uncompressed. A document that declares a DTD is refused. The XML is loaded with `LIBXML_NONET` and entities are not substituted. Macros, embedded objects, images, headers, and footers are not read.

Paragraphs become one-cell rows. Table rows keep one cell per column, so a label cell and its value cell stay paired. Tracked deletions and field codes are skipped. Text boxes are read once (the drawing fallback copy is ignored).

### Reading PDF

`PdfTextReader` uses **smalot/pdfparser v2.12.5** (LGPL-3.0), bundled unmodified under `lib/smalot-pdfparser/` and loaded only when a PDF is read. It needs PHP `mbstring`, `zlib`, and `iconv`. Without them, a PDF reports `unsupported` and staff enter the details by hand.

Up to 40 pages are read. A tab in a line is treated as a column gap. Fillable-form values are read from field dictionaries, using the field's description where it has one and its name otherwise. Checkbox and radio states are not read.

There is no text recognition. A scanned or photographed form has no text layer and reports `no_text`. An encrypted or damaged PDF reports `unreadable`. Parser exceptions are caught and never shown, because their messages can quote file content.

---

## Inbox item

| Column | Value |
| --- | --- |
| `source_provider` | `manual` |
| `mailbox_identifier` | `document-upload` |
| `provider_message_id` | `upload-<sha256 of the file>` |
| `subject` | `Uploaded referral form: <file name>` |
| `sender_name`, `sender_email`, `body_preview` | `NULL` |
| `attachment_count` | 1 |
| `detection_status` / `detection_reason` | `likely` / `staff_uploaded_referral_form` |
| `status` | `needs_review`, with `reviewed_by` set to the uploader |

No form content is copied into the Inbox row. The file name is the only detail taken from the upload.

The attachment row is `stored` with `private_path`, `sha256`, and `size_bytes`. After conversion it is `promoted`.

### Duplicates

The dedupe key comes from the file's hash. Uploading the same file again does not create a second item or store a second copy: the user is taken to the item the file already created, with a message that says whether it is waiting for review or already converted.

If that item was ignored or marked duplicate, the same file may be uploaded afresh. The new item uses `upload-<sha256>-r1`, then `-r2`, and so on, so there is never more than one open item per file.

If the file could not be stored after the Inbox row was created, the row is moved to `error`. Uploading the file again repairs that item.

---

## Field extraction

`ReferralFormFieldExtractor` is deterministic. No AI and no external service is used, in line with Phase 5D.3.

A value is taken only from beside, beneath, or after a recognised label. Three layouts are read:

| Layout | Example |
| --- | --- |
| Label and value on one line | `Date of Birth: 14/03/1942`. Several pairs on one line are split |
| Label cell and value cell | A two-column table, a four-column table, a header row with values beneath, or a tab-aligned line |
| Label above value | `Client Name` on one line, the name on the next |

Fillable PDF fields are read as label/value pairs.

Labels are compared after normalisation: case, numbering, a trailing colon, and a trailing bracketed note are ignored, so `3. Date of Birth (dd/mm/yyyy):` matches `date of birth`.

### Sections

Plain labels (Name, First name, Surname, Telephone, Mobile, Email, Address, Town, Postcode, Organisation, Relationship) appear more than once on most forms. They are assigned by the section heading above them.

| Section | Plain labels become |
| --- | --- |
| Client heading, or no heading yet | Client fields |
| Referrer heading | Referrer fields |
| Next of kin, emergency contact, GP, declaration, consent, office use, and similar | Ignored |
| Care needs heading | Ignored |

Labels that name their subject (`Client Name`, `Referrer Email`, `Social Worker`) are assigned regardless of section.

### Fields

| Suggested field | Notes |
| --- | --- |
| `client_name` | A full name, or first name and surname joined when the form gives them separately |
| `client_date_of_birth` | Day-first dates: `14/03/1942`, `14-03-42`, `14.03.1942`, `1942-03-14`, `14 March 1942`, `14th Mar 1942`. Stored as `YYYY-MM-DD`. Future dates and years before 1900 are dropped |
| `client_phone`, `referrer_phone` | 8 to 15 digits |
| `client_email`, `referrer_email` | Validated with `is_email()` |
| `address_line_1`, `address_line_2`, `city`, `postcode` | From separate labels, or split from one address block. A UK postcode is pulled out of the block |
| `referrer_name`, `referrer_organisation`, `relationship_to_client` | |
| `care_requirements` | Multi-line. Several care sections are joined |
| `care_start_date` | Same date formats; years 2000 to five years ahead |
| `service_hint`, `priority_hint` | Advisory only. They never select a service type or a priority |

NHS number, gender, ethnicity, religion, GP, next of kin, diagnosis, and medication are recognised only so that they end the previous value. They are not captured as fields. A `Medication:` or `Diagnosis:` line inside a care-needs answer stays part of that answer.

### States

The result uses `ReferralInboxCandidateField`, with the same meaning as Phase 5D.3:

| State | Preparation form |
| --- | --- |
| `single` | Prefilled, labelled “Suggested from uploaded form” |
| `none` | Blank |
| `ambiguous` | Blank, with a warning and the alternatives listed |

Two different values for one field are `ambiguous`. Neither is chosen. A form that gives both a telephone and a mobile number is ambiguous for the client phone, and staff pick one.

Placeholders are treated as empty: `N/A`, `None`, `TBC`, `Unknown`, fill lines, and Word's “Click or tap here to enter text.”

When an Inbox item has both message candidates and a form, a message candidate wins if it is the only one. If both exist and disagree, the field is left blank and both values are listed.

### Label settings

**wp-admin → J&M Referrals → Settings → Referral Form Upload: Field Labels** lists the wording for each field, one label per line, with two lists for the headings that start the client section and the referrer section. Option `jmrs_referral_form_labels`. **Reset to Built-in Lists** deletes the option.

Labels are stored normalised, limited to 80 characters and 80 per field, and refused if they contain markup. They are matched as whole labels and are never used as patterns. Requires `MANAGE_SETTINGS`.

A change applies the next time a form is opened in Prepare Referral, because suggestions are read from the stored file each time and are not saved.

---

## Preparation screen

An **Uploaded form** panel shows the file name, how many fields were filled in, and a collapsed copy of the text read from the form, for checking and copying. That text is rendered for the current response only.

New optional fields, shown for every Inbox item: Date of Birth, Address Line 1, Address Line 2, Town / City, Postcode, Referrer Phone, Relationship to Client, Care Start Date, Care Requirements. For an email-style item they start blank.

Date of birth must be a real date and not in the future. Care start date uses the existing `ReferralValidator` rule. Service type, referral source, and priority are still required and still start unselected.

---

## Conversion

The new fields are passed to `ReferralService::create_database_effects()`, which already wrote these columns for public intake. `submission_channel` stays `admin`.

After commit, each `stored` attachment is added to the referral with `ReferralDocumentService::attach_private_file()`, which checks that the path resolves inside the private root and that the file still matches its recorded checksum. The referral document row points at the same private file; no copy is made. The activity row is the ordinary `document_uploaded`.

If attaching fails, the referral and the accepted Inbox link remain, the attachment stays `stored`, and the Inbox detail shows a warning asking staff to upload the form from the referral.

---

## What is stored

| Stored | Not stored |
| --- | --- |
| The uploaded file, in private storage | The text read from the form |
| File name, MIME type, size, SHA-256, private path | Field suggestions |
| The fields a member of staff confirmed, on the referral | Alternatives, evidence, confidence |

Nothing is written to options, transients, sessions, cookies, or logs.

---

## Boundaries

- No schema change. No new capability. No new table or column.
- No AI, no external API, no text recognition, no Graph, no mailbox polling.
- No automatic referral creation. No change to `ReferralService::create()` or its callers.
- The wp-admin Add Referral screen and public intake are not changed.
- Inbox attachments are still not downloadable from the Inbox detail screen. The form is downloadable from the referral once it is created.
- An uploaded form that is ignored, or left unconverted, stays in private storage. There is no purge, in line with [`DATA_RETENTION_POLICY.md`](../DATA_RETENTION_POLICY.md).

## Known limits

- Accuracy depends on the form's wording and layout. The built-in labels were tuned against made-up forms, not a real care-provider form.
- PDF text loses table structure. Two columns can run into one line, and a long value can wrap. Word files read more reliably than PDFs of the same form.
- Scanned or handwritten forms cannot be read.
- Tick boxes are read only when the tick is a character in the text (☒, ☑, `[x]`). Word content-control and legacy form checkboxes are not read.
- Dates are read day-first. A US-style `03/14/1942` is corrected only because 14 cannot be a month.
- Postcodes are recognised in UK format only.
