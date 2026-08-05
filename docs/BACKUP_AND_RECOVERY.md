# Backup and Recovery — JM Referral System v1.0.0

---

## What to back up

| Asset | Why |
| --- | --- |
| WordPress database | Referrals, visits, assessments, settings, users |
| `wp-content/uploads/jmrs-private/` | Private referral documents (not Media Library) |
| `wp-content/uploads/` (rest) | Theme/media; any legacy referral attachments |
| Plugin files | Only needed for rollback to a specific build |

Attachment-only backups **miss** private documents.

---

## Recommended schedule

- Daily automated DB backup (host or plugin)
- Daily or continuous `uploads` including `jmrs-private`
- Pre-upgrade full backup (DB + uploads + note plugin version)
- Periodic restore test on staging

---

## Before upgrades or migrations

1. Record current plugin version and `jmrs_db_version` option value
2. Snapshot DB
3. Snapshot `jmrs-private`
4. Proceed with upgrade
5. Smoke-test list, view, download, public form, portal

---

## Recovery scenarios

### Accidental data change / bad upgrade

1. Put site in maintenance if needed
2. Restore DB backup
3. Restore `jmrs-private` (and other uploads if needed)
4. Restore matching plugin ZIP if schema expectations differ
5. Clear caches; verify `jmrs_db_version`

### Lost private files only

Restore `jmrs-private` from backup. Database rows alone cannot recreate file bytes.

### Plugin deleted, data preserved

Default uninstall keeps tables and private files. Reinstall the same major version ZIP and activate; migrations no-op if already current.

### Full wipe (disposable sites only)

Only when `JMRS_DELETE_DATA_ON_UNINSTALL` is explicitly enabled. Never enable on production without a verified backup.

---

## Rollback checklist

- [ ] Previous plugin ZIP available
- [ ] DB backup from before change
- [ ] Uploads / `jmrs-private` backup
- [ ] SMTP still works after restore
- [ ] Rewrite rules: re-save permalinks or re-save portal settings if portal URLs 404

---

## Related documents

- `docs/INSTALLATION_GUIDE.md`
- `docs/DATA_RETENTION_POLICY.md`
- `docs/RELEASE_CHECKLIST.md`
