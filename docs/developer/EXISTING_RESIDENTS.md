# Existing Residents — Phase 5F.1

Registers clients who already receive care (for example when a provider first moves onto the platform)
without sending them through interest, assessment, package costing and Local Authority decision.

**Database:** `2.34.0` · **Portal rewrite:** `1.2.11` · **Capability:** `jmrs_register_existing_residents`

---

## What staff can do

| Route | Path | Purpose |
| --- | --- | --- |
| `residents_existing` | `/staff-portal/residents/existing/` | Overview: add one, import many, property / free-bedroom reference |
| `resident_existing_new` | `/residents/existing/new/` | Single-resident form |
| `resident_import` | `/residents/existing/import/` | Upload → check (preview) → confirm → result |
| `resident_import_template` | `/residents/existing/import/template/` | CSV template download |

Excel template: `assets/templates/existing-residents-import-template.xlsx` (sheets *Residents* and *Instructions*;
dropdowns for Care setting and Funding type; date and text column formats).

## Data model

An existing resident is a normal care record on `jmrs_referrals`, so occupancy, clinical modules, dashboards and
the client list work unchanged. Decision record:

| Field | Value for an existing resident |
| --- | --- |
| `record_origin` | `existing_resident` (NULL for every genuine referral) |
| `submission_channel` | `existing_resident` (label “Existing resident (registered)”) |
| `workflow_stage_id` | canonical `care_commenced` |
| `workflow_stage_entered_at` | the real care start date (00:00) |
| `care_commenced_at` / `care_start_date` | the real care start date; `care_commenced_by` stays NULL (not known) |
| `admission_date` | the day they joined the service |
| `care_setting` | `supported_living` or `own_home` |
| `status` | `in_progress` (same as Confirm Care Commenced) |
| `registered_at` / `registered_by` | when and who added them |
| `registration_batch` | shared reference for every row of one bulk import (`import-YYYYMMDD-HHMMSS-xxxxxx`); NULL for single adds |

Related rows written in the same transaction:

- **`jmrs_occupancies`** (Supported Living only): active placement in the chosen bedroom. `move_in_date` = the
  “moved into current bedroom” date, defaulting to the admission date (it may be later after a room move).
- **`jmrs_funding_arrangements`** (new, when funding is given): `funding_type` (`local_authority`, `nhs_chc`,
  `joint`, `self_funded`, `other`), `local_authority_id` *or* `funder_name`, `funding_reference`, `start_date`,
  `weekly_amount`, `weekly_support_hours`, `package_summary`, `source = existing_resident`.
- **`jmrs_referral_stage_history`**: one row, `from NULL → care_commenced`, `change_type = existing_resident`,
  reason “Registered as an existing resident. Care started …”.
- **`jmrs_referral_activity`**: one row, action `existing_resident_registered`, describing admission and care start
  dates, placement, funding type, owner, and that no referral, assessment, costing or LA decision history was created.

Deliberately **not** written: `created` / `pipeline_started` / `placement_started` activity, interest response,
assessments, package costs, Local Authority decisions, notifications or emails.

Funding arrangements are separate from `jmrs_referral_la_decisions` on purpose: a decision is an acquisition event,
an arrangement is funding that is in place. The record page shows the arrangement for any client that has one.

## Rules (`ExistingResidentValidator`)

Applied identically to the form and to every import row.

- Required: first name, last name, date of birth (past), care setting, admission date, care start date.
- Admission and care start dates cannot be in the future (someone not yet started is a referral) and cannot be
  before the date of birth. Care start may be before or after admission.
- Supported Living: an active bedroom in an active property with no active resident. Requires the Supported Living module.
  “Moved into current bedroom” cannot be before admission or in the future.
- Own Home: address line 1 and postcode required. Property / bedroom must be blank.
- Funding is optional. If any funding field is filled, a funding type is required; Local Authority and Joint
  funding need an authority (from the directory, or a name typed as Other funder).
- The same first name + last name + date of birth may not already exist (any archive state) or appear twice in one file.
- Dates accept `YYYY-MM-DD`, `DD/MM/YYYY`, `D/M/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`, and Excel date serials.
  Two-digit years and impossible dates are refused, never guessed.

## Bulk import (`ResidentImportService`)

1. **Preview.** The upload (`.xlsx` or `.csv`, ≤ 1 MB, ≤ 500 rows) is read straight from PHP's temporary upload
   and never stored. Content must match the extension (xlsx = zip signature; a zip named .csv is refused).
   `SpreadsheetReader` reads the *Residents* sheet (or the first sheet) without evaluating formulas or links; XML
   with a DOCTYPE is refused; zip entries over 20 MB are refused. CSV may be UTF-8 (with or without BOM),
   Windows-1252, comma or semicolon separated.
2. `ResidentImportParser` matches headings loosely (case, punctuation, `*`, aliases), skips rows whose first name
   starts with “EXAMPLE”, and resolves names to IDs: property + bedroom, Local Authority (name or slug; otherwise
   kept as written with a note), service type, owner (email or username, must be assignable).
3. Every row is validated, plus file-level checks (same bedroom twice, same person twice). Errors are shown per row
   with the spreadsheet row number and column name, and list valid options (e.g. property names).
4. Only when **every** row is valid is a one-time token issued. The checked rows are kept in a transient
   (`jmrs_res_import_<md5(token|user)>`, 30 minutes) bound to the uploading user.
5. **Confirm** re-validates everything and writes all rows in **one transaction**, holding the referral-number lock
   and locking each bedroom row (`FOR UPDATE`). Any failure — including a bedroom filled since the preview — rolls
   back every row. The transient is deleted on success; a second confirm with the same token is refused.

Single adds use the same service (`register_one` → `register_many` with one row and no batch).

`TransactionEngineGuard::tables_are_transactional()` must confirm InnoDB for referrals, activity, stage history,
occupancies, bedrooms and funding arrangements, otherwise nothing is written.

## Reporting

Existing residents were never referred, so referral (acquisition) figures leave them out; caseload and occupancy
figures include them.

| Excluded (`record_origin` filter) | Included |
| --- | --- |
| Acquisition report: cohort, funnel, outcomes, timings, export (`AcquisitionReportRepository::append_assigned_scope`) | Active clients / caseload, clients by care setting |
| Referrals received, by month / service / workflow stage / priority (`ReportRepository::append_genuine_referrals`) | Occupancy, vacancy, estate summary, home dashboards |
| Management dashboard acquisition KPI + Care Commenced count; pipeline dashboard terminal counts (`count_active_by_pipeline_slugs(..., true)`) | Client list, visits, care plans, medications, alerts |

`append_assigned_scope` now inserts its conditions before a trailing `GROUP BY` / `ORDER BY`. Previously the
assignee condition was appended after them, producing invalid SQL for scoped users on the stage distribution,
assessment outcome and export queries.

Placement-movement reporting is activity based and does not count `existing_resident_registered`, so registrations
do not appear as move-ins.

## Permissions

`jmrs_register_existing_residents` — WordPress administrator, Platform Administrator, Referral Manager.
Not Care Coordinator, Assessor or Support Worker. Every route checks it; the registration service checks it again
for the acting user. Forms use nonces (`jmrs_register_existing_resident`, `jmrs_resident_import`).

## Correcting mistakes

Records are normal care records: edit details from the record, transfer or end the placement from Occupancy.
A record added in error should be **archived** (the registration timeline entry means it is not eligible for
permanent deletion, in line with the archive-first retention policy). The import reference on each record
identifies everything added by one import.

## Code map

| Class | Role |
| --- | --- |
| `Residents\ExistingResident` | Constants, funding type labels and spreadsheet wording |
| `Residents\DateInput` | UK date parsing |
| `Residents\ExistingResidentValidator` | Shared rules (single + batch) |
| `Residents\ExistingResidentRegistrationService` | All-or-nothing write |
| `Residents\FundingArrangementRepository` | `jmrs_funding_arrangements` |
| `Residents\Import\SpreadsheetReader` | CSV / xlsx rows |
| `Residents\Import\ResidentImportColumns` | Column definitions, heading matching, CSV template |
| `Residents\Import\ResidentImportParser` | Names → IDs, row errors and notes |
| `Residents\Import\ResidentImportService` | Preview token, confirm, discard |
| `Portal\Residents\ExistingResidentHandler` | Portal routes |
| `templates/portal/residents/*`, `templates/portal/referrals/partials/existing-resident.php` | Screens and record panel |
