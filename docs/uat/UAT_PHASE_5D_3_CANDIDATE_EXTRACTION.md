# UAT — Phase 5D.3 Candidate referral field extraction

**Product:** 1.5.0  
**Database:** 2.33.0 (unchanged — no migration)  
**Portal rewrite:** 1.2.8 (unchanged — no new route)  
**Previous checkpoint:** `5b1d41311da9948619941653e33189a905e90a19` (Phase 5D.2)  
**Branch:** `feature/5d-referral-detection`

**Manual UAT date:** **2026-09-29**  
**Overall result:** **PASS**

**Versions confirmed after UAT:** Product **1.5.0** · Database **2.33.0** · Rewrite **1.2.8**

**UAT method:** A temporary admin-only staging runner prepared scoped fixtures and in-memory checks. The runner was completely removed before this checkpoint. No live UAT page remains.

**Scope:** Advisory candidate fields from stored Inbox metadata. Results stay in memory.

**Out of scope:** Schema change; new portal route; production button; Accept Referral; referral creation; AI/external API; Microsoft Graph/OAuth/polling/webhooks; develop/main merge; tag; package; deploy. The Phase 5C.2 stash was not applied.

---

## Case A — Full explicit labels

| Check | Result |
| --- | --- |
| client_name = Jane Example | **PASS** |
| client_email = jane@example.org | **PASS** |
| client_phone = +44 7700 900123 | **PASS** |
| referrer_name = Alex Referrer | **PASS** |
| referrer_email = alex.referrer@example.org | **PASS** |
| referrer_organisation = UAT 5D3 Alpha Council | **PASS** |
| service_hint = supported_living | **PASS** |
| No values persisted to Inbox | **PASS** |

## Case B — Generic sender name

Sender name: Referrals Team.

| Check | Result |
| --- | --- |
| referrer_name = none | **PASS** |
| referrer_email still extracted | **PASS** |
| No person name invented | **PASS** |

## Case C — Client email versus sender

| Check | Result |
| --- | --- |
| client_email = person@example.org | **PASS** |
| referrer_email = social.worker@example.org | **PASS** |
| Values remained distinct | **PASS** |

The sender email is never automatically treated as the client email.

## Case D — Service hints

| Check | Result |
| --- | --- |
| Supported Living → supported_living | **PASS** |
| Domiciliary Care → home_care | **PASS** |
| Residential Placement → residential_care | **PASS** |
| No service_type_id selected | **PASS** |

Service hints remain advisory canonical hints.

## Case E — Priority hint

| Check | Result |
| --- | --- |
| Urgent Referral → urgent | **PASS** |
| Neutral wording → no priority hint | **PASS** |
| No automatic medium default | **PASS** |

## Case F — Conflicting client emails

| Check | Result |
| --- | --- |
| Multiple labelled emails detected | **PASS** |
| State = ambiguous | **PASS** |
| No single value chosen | **PASS** |

## Case G — Conflicting phone values

| Check | Result |
| --- | --- |
| Multiple labelled phone values detected | **PASS** |
| State = ambiguous | **PASS** |
| No silent first or last winner | **PASS** |

## Case H — Invalid email

| Check | Result |
| --- | --- |
| Invalid labelled email ignored safely | **PASS** |
| No exception | **PASS** |
| No valid client-email candidate returned | **PASS** |

## Case I — Unlabelled capitalised name

The subject contained Jane Example. There was no client-name label.

| Check | Result |
| --- | --- |
| client_name remained none | **PASS** |
| No inference from capitalisation | **PASS** |

## Case J — Cleared authority

Stored `local_authority_id` was NULL and origin was `cleared`.

| Check | Result |
| --- | --- |
| referrer_organisation = none | **PASS** |
| Sender matcher not used to repopulate organisation | **PASS** |
| Human clear respected | **PASS** |

## Case K — Suggested authority

| Check | Result |
| --- | --- |
| Stored authority returned as organisation candidate | **PASS** |
| Evidence = suggested_authority | **PASS** |
| Not represented as staff-confirmed | **PASS** |

## Case L — Legacy authority

| Check | Result |
| --- | --- |
| Stored authority returned as organisation candidate | **PASS** |
| Evidence = linked_authority_unknown_origin | **PASS** |

## Case M — Attachment service hint

Filename: supported-living-referral-form.pdf.

| Check | Result |
| --- | --- |
| service_hint = supported_living | **PASS** |
| No attachment binary read | **PASS** |

## Case N — No signals

| Check | Result |
| --- | --- |
| client_name none | **PASS** |
| client_email none | **PASS** |
| client_phone none | **PASS** |
| service_hint none | **PASS** |
| priority_hint none | **PASS** |
| No defaults invented | **PASS** |

## Case O — Real Inbox, no mutation

Extraction ran repeatedly against a real scoped Inbox fixture.

| Check | Result |
| --- | --- |
| Lifecycle unchanged | **PASS** |
| Detection unchanged | **PASS** |
| Local Authority unchanged | **PASS** |
| Authority provenance unchanged | **PASS** |
| reviewed_by unchanged | **PASS** |
| reviewed_at unchanged | **PASS** |
| No database write | **PASS** |

The extractor is read-only.

## Case P — No persistence or logging

| Check | Result |
| --- | --- |
| No candidate JSON in the Inbox table | **PASS** |
| No candidate data in wp_options | **PASS** |
| No candidate data in transients | **PASS** |
| No referral activity created | **PASS** |
| No referral created | **PASS** |
| No candidate values written to logs | **PASS** |

Extraction results remain in memory only.

## Case Q — Bounds

| Check | Result |
| --- | --- |
| Oversized labelled values handled safely | **PASS** |
| Name candidate bounded | **PASS** |
| Email candidate bounded and validated | **PASS** |
| Phone candidate bounded | **PASS** |
| No fatal or unbounded processing | **PASS** |

## Privacy and security

| Check | Result |
| --- | --- |
| No raw MIME read | **PASS** |
| No raw HTML fetched | **PASS** |
| No attachment download | **PASS** |
| No OCR | **PASS** |
| No external API or AI | **PASS** |
| Evidence uses structural codes only | **PASS** |
| Candidate result has no string dump | **PASS** |

Candidate values are not included in exception text or logging.

## Production boundaries

| Check | Result |
| --- | --- |
| No new Staff Portal route | **PASS** |
| No new production button | **PASS** |
| No Accept Referral action | **PASS** |
| No ReferralService::create() | **PASS** |
| No lifecycle mutation | **PASS** |
| No authority mutation | **PASS** |
| No detection mutation | **PASS** |

## Regression

| Check | Result |
| --- | --- |
| 5D.1 detection still works | **PASS** |
| 5D.2 authority review still works | **PASS** |
| Referral Inbox unchanged | **PASS** |
| Referral 10 unchanged | **PASS** |
| Existing referrals unchanged | **PASS** |
| Local Authority directory unchanged | **PASS** |
| Microsoft 5C.2 stash still present | **PASS** |

## Versions

| Check | Result |
| --- | --- |
| Product 1.5.0 | **PASS** |
| Database 2.33.0 | **PASS** |
| Portal rewrite 1.2.8 | **PASS** |
| No migration | **PASS** |

---

## Cleanup

Scoped to `source_provider = fixture` and mailbox identifiers beginning `uat-5d3-`. No real Inbox data was removed.

| Item | Count |
| --- | --- |
| Inbox fixtures removed | 1 |
| Attachment fixtures removed | 0 |
| Remaining scoped Inbox fixtures | 0 |

**Result:** **PASS**

---

## Sign-off

| | |
| --- | --- |
| Date | 2026-09-29 |
| Overall | **PASS** |
