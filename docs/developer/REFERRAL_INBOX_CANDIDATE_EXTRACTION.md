# Referral Inbox candidate extraction (Phase 5D.3)

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.8 (unchanged — no new route)

`ReferralInboxCandidateExtractor` builds advisory referral-field suggestions from data already stored on an Inbox item. The result stays in memory. This phase does not create a referral and does not show candidates in the Staff Portal.

## Read-only inputs

The extractor may read:

- `sender_name`, `sender_email`
- `subject`, `body_preview`
- attachment filenames
- the stored Local Authority id and origin
- `detection_status` and `detection_reason`, as context only

It does not fetch a raw message, download an attachment, parse a binary file, or call `LocalAuthoritySenderMatcher`. A human clear is not replaced by a fresh sender match.

Detection classification does not force a service hint or suppress an explicitly labelled client field.

## Fields

| Field | Where it comes from |
| --- | --- |
| `client_name` | Explicit body labels only: Client, Client Name, Service User, Service User Name, Person, Name of Client |
| `client_email` | Explicit body labels only: Client Email, Service User Email, Email Address. Never the sender address |
| `client_phone` | Explicit body labels only: Client Phone, Telephone, Mobile, Contact Number, Phone |
| `referrer_name` | `sender_name`, unless it is a generic mailbox label |
| `referrer_email` | A valid `sender_email` |
| `referrer_organisation` | The stored Local Authority name |
| `service_hint` | Phrase match in subject, body preview, or filename. Not a service type id |
| `priority_hint` | Explicit urgent or high phrases. Not a saved referral priority |

Date of birth, NHS number, diagnosis, care needs, medication, financial data, and address are out of scope.

Labels are fixed, case-insensitive, and require `:` or `-`. Matching is line-bounded inside `body_preview`. There is no caller-supplied pattern.

## Field states

Each field is `none`, `single`, or `ambiguous`.

A single field has one value, a source, a structural evidence code, and a band of `strong` or `moderate`. There is no numeric probability.

Two different explicit values for the same field are `ambiguous`. The result keeps the alternatives and does not choose one. Invalid emails and structurally unsafe phone strings are dropped. If nothing valid remains, the state is `none`.

Evidence codes name the structure, for example `body_label_client_name`, `sender_email_referrer`, `subject_service_hint`, `staff_confirmed_authority`. They do not quote the message.

## Organisation provenance

| Stored origin | Candidate |
| --- | --- |
| `confirmed` | Authority name, evidence `staff_confirmed_authority`, band `strong` |
| `suggested` | Authority name, evidence `suggested_authority`, band `moderate` |
| `NULL` with an id | Authority name, evidence `linked_authority_unknown_origin`, band `moderate` |
| `cleared`, or no id | No organisation. The supplied name is ignored |

## Hints

Service phrases map only to `supported_living`, `home_care`, or `residential_care`. Staff still choose a real service type later.

Priority phrases map only to `urgent` or `high`. “Please review when possible” and “as soon as possible” do not set a priority. Unclear text stays empty. The extractor does not default to `medium`.

Generic sender names, including Referrals Team, Admissions, Duty Team, Care Team, Placement Team, Commissioning, and Inbox, are not proposed as a person’s name. The sender email can still be the referrer email.

## Bounds and safety

Names are limited to 255 characters. Emails use WordPress sanitisation and `is_email()`, with a 190-character cap. Phones keep a leading `+`, digits, spaces, parentheses, and hyphens, and must contain 8 to 15 digits. Dates and values that contain letters are not treated as phones.

`extract($inboxId)` returns `not_found` when the row is missing. `extractFromFields()` evaluates a snapshot the caller already loaded. Neither method writes to Inbox tables, options, transients, sessions, logs, or referrals.

The result objects have no string cast. Candidate values are not written into exceptions.

## Boundaries

No schema change. No new portal route. No production template change. No AI or external API. No Graph, OAuth, webhook, delta, or mailbox polling. No `ReferralService::create()`.
