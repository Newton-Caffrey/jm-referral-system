# Data Retention Policy (JM Referral System)

This document describes the **application behaviour** of the JM Referral System regarding archiving and deletion of referrals. It is an operational policy for the software, not legal advice and not a claim of regulatory compliance.

JM should confirm retention periods, lawful bases, and erasure obligations with its legal and compliance advisers for the jurisdictions and contracts that apply.

## Archive-first policy

Referrals that have linked clinical or operational records must not be permanently deleted through the normal admin UI.

Authorized users with `jmrs_archive_referrals` may **archive** a referral instead. Archiving:

- Sets `archived_at`, `archived_by`, and a required `archive_reason`
- Preserves all child records (notes, documents, assessments, care plans, visits, medications, activity, and related rows)
- Makes the referral **read-only** for clinical and operational mutations
- Keeps historical activity readable

Authorized users with `jmrs_restore_referrals` may restore an archived referral (clears archive fields and returns it to the active caseload filters).

## Permanent deletion rules

Permanent deletion requires `jmrs_delete_referrals` plus record-level access (`AccessPolicy::can_edit_referral`).

Deletion is allowed **only** when the retention service finds **no blocking dependent records**.

If any blocking dependency exists, deletion is refused with a generic message directing the user to archive instead. There is no partial or cascading cleanup of clinical child rows during normal deletion.

### Dependent records (blocking)

- Internal notes
- Documents
- Assessments
- Care plans
- Care-plan versions
- Care-plan reviews
- Care-team assignments
- Schedules
- Visits
- Visit tasks
- Medications
- Medication administrations
- Activity entries **other than** the bootstrap `created` / “Referral created” row(s)

The initial “Referral created” activity alone does not block permanent deletion. When an empty referral is deleted, remaining bootstrap activity for that referral may be removed as safe referral-owned metadata.

## Restore behaviour

Restore clears archive metadata only. It does not rewrite clinical content. After restore, normal mutation rules apply again.

## Timed retention deletion

The plugin does **not** automatically delete or anonymise referrals after a retention period. Any timed purge would be a future, explicitly designed feature with legal review.

## Backup expectations

Operators should maintain backups of:

1. The WordPress database (including all `{$wpdb->prefix}jmrs_*` tables)
2. Private document files under `uploads/jmrs-private/`

Attachment-only backups do **not** cover private document storage.

## Legacy orphan-record risks

Earlier versions could leave child rows if a referral parent was deleted without archive gating, or if data was manipulated outside the plugin. Settings → **Data Integrity Check** reports counts of orphaned references and certain broken links. It does **not** auto-repair.

Legacy Media Library attachments created before private storage are **not** deleted by uninstall or by referral archive/delete flows. Manual review is required.

## Uninstall behaviour

Default uninstall:

- Removes custom JM roles and plugin capabilities from roles (including Administrator)
- **Preserves** custom tables and private files

Archive and approved retention processes are the **supported production** path. Uninstall is not a retention workflow.

Optional wipe (disposable / migration sites only):

```php
define('JMRS_DELETE_DATA_ON_UNINSTALL', true);
```

When strictly `true`, uninstall attempts to drop listed custom tables, delete JMRS options/transients, and remove the private storage directory. It does **not** delete Media Library attachments.

**Important:** the opt-in destructive constant is an administrative/development operation. It is **not** documented as complete purge coverage of every current schema table, and must not be relied on as a production erasure mechanism. Complete purge coverage should be reviewed separately before any reliance on wipe behaviour.

Multisite: uninstall runs in the blog context where the plugin is deleted; plan per-site cleanup carefully.

## Recommendation

Confirm with JM’s legal/compliance advisers:

- How long archived and active records must be retained
- When permanent erasure is required or prohibited
- How backups and host snapshots interact with erasure requests
- Staff training for archive vs delete

Do not treat this plugin document as evidence of GDPR, UK GDPR, HIPAA, CQC, or other regulatory compliance.
