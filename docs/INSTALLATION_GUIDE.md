# Installation Guide — JM Referral System

Professional installation and go-live guide for WordPress hosts.

**Product version:** 1.5.0
**Database schema:** 2.29.0
**Portal rewrite:** 1.2.7

---

## 1. Requirements

| Requirement | Recommendation |
| --- | --- |
| WordPress | 6.0 or later |
| PHP | 8.0 or later (8.1+ preferred) |
| MySQL / MariaDB | Compatible with WordPress |
| Extensions | Standard WP stack (`mysqli`, `json`, `mbstring`) |
| HTTPS | Strongly recommended for login, portal, and uploads |
| Mail | Working `wp_mail` / SMTP (for notifications) |

Disk space: allow for database growth plus `wp-content/uploads/jmrs-private/` (private documents).

<!-- Screenshot: hosting PHP version panel -->

---

## 2. Pre-install checklist

- [ ] Full database backup
- [ ] Backup of `wp-content/uploads/`
- [ ] Staging site available (recommended before production)
- [ ] SMTP plugin or host mail configured and tested
- [ ] WordPress Administrator account ready

---

## 3. Upload and activation

### Option A — WordPress Admin ZIP upload

1. Download the official release ZIP (`jm-referral-system` folder at the root of the archive).
2. In WordPress: **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP → **Install Now** → **Activate**.

### Option B — SFTP / file manager

1. Extract the ZIP.
2. Upload the `jm-referral-system` directory to `wp-content/plugins/`.
3. Activate under **Plugins**.

### What activation does

- Runs database migrations (`jmrs_db_version` → `2.29.0`)
- Registers JM roles and grants Administrator capabilities
- Ensures private document storage is ready
- Flushes rewrite rules once (staff portal routes when enabled later)

<!-- Screenshot: Plugins screen showing JM Referral System activated -->

---

## 4. First administrator setup

1. Sign in as a WordPress **Administrator**.
2. Open **J&M Referrals** in the admin menu.
3. Confirm the **Dashboard** loads without errors.
4. Optionally create or assign users to JM roles:
   - JM Administrator
   - Referral Manager
   - Care Coordinator
   - Assessor
   - Support Worker

Permissions are capability-based. See `docs/ADMINISTRATOR_GUIDE.md`.

---

## 5. SMTP and email

The plugin uses WordPress `wp_mail` for:

- Referral created / assigned / status changed
- Public intake ops notification
- Public referrer confirmation

Configure SMTP (host panel or a WordPress SMTP plugin) **before** relying on notifications.

Test: create a referral assigned to yourself and confirm the email arrives.

---

## 6. Private document storage

New documents are stored under:

`wp-content/uploads/jmrs-private/`

- Not served as public Media Library URLs
- Downloads use secure plugin links
- Apache hosts get `.htaccess` deny rules (nginx needs equivalent deny rules)

**Always back up this directory** with the database.

After go-live, run **Settings → Private Document Migration** if legacy Media Library documents remain.

---

## 7. Public referral page

1. **J&M Referrals → Settings → Public Referral**
2. Enable the public form.
3. Set privacy URL, consent version, notification email, branding, upload limits.
4. Create a WordPress **Page** (e.g. “Refer a Client”).
5. Add shortcode:

```
[jmrs_public_referral_form]
```

6. Publish and test a submission on staging.

Details: `docs/PUBLIC_REFERRAL_GUIDE.md` and `docs/PUBLIC_REFERRAL_INTAKE.md`.

<!-- Screenshot: page editor with shortcode -->

---

## 8. Staff portal (optional)

1. **Settings → Staff Portal**
2. Enable Staff Portal (disabled by default).
3. Set portal name, colours, logo, base path (default `staff-portal`).
4. Save (rewrite rules flush once when enable/path changes).
5. Visit `https://yoursite.example/staff-portal/`
6. Keep **Redirect JMRS Staff Away From wp-admin** **off** until thoroughly tested.

Management Dashboard (`/management/`, Phase 4A + **4D.1**–**4I.1**): commercial roles only; Operations tab uses real scope-aware JMRS aggregates (14-day upcoming meetings/assessments; Package Costing; Local Authority Decision awaiting + outcome counts from latest decision row; archived excluded; GET read-only). Completed assessments are clinical/scheduling read-only (4E.1). Sent Package Costs are terminal/read-only (4F.1). Recorded LA decisions are terminal/read-only with notes displayed on the panel (4G.1). Transition Planning / Care Commencement hardened as derived readiness + record-once commence (4H.1); no target-home reservation or checklist; capacity semantic unchanged. Operations UI polish (4I.1). Product **1.5.0** · DB **2.29.0** · rewrite **1.2.7**. Focused phase UAT records remain in `docs/uat/`; full Phase **4J.1** regression is still required before production. Several paths remain code-reviewed-only until 4J.1 (Package Cost email send; LA Declined / Not Proceeding; LA status-change email; Own Home commencement).

Details: `docs/STAFF_PORTAL.md` and `docs/STAFF_USER_GUIDE.md`.

---

## 9. Recommended first-week settings

- Service types and workflow stages configured
- Public form off until content/legal review complete
- Portal off or redirect off until UAT complete
- Integrity check run once under Settings
- Backup schedule confirmed (DB + uploads + `jmrs-private`)

---

## 10. Upgrading

### General

1. Backup DB + uploads (including `jmrs-private`).
2. Deactivate plugin (optional but safer on some hosts).
3. Replace plugin files with the new release ZIP (keep `vendor/` included). Do **not** uninstall to upgrade.
4. Activate / load admin so `Migrator::maybe_migrate()` runs.
5. Smoke-test dashboard, one referral view, document download, public form (if used), portal (if used).

Schema bumps only when `Migrator::DB_VERSION` changes. Product version and DB version are independent.

### Upgrade from production v1.4.0 → v1.5.0

1. Back up site files.
2. Back up the database.
3. Back up the current JMRS plugin directory or ZIP.
4. Do **not** uninstall the existing plugin.
5. Replace plugin files with the **1.5.0** package (root folder `jm-referral-system/`; include `vendor/autoload.php`).
6. Activate or load the plugin.
7. Verify database version **2.29.0**.
8. Verify meeting tables (`jmrs_referral_meetings`, `jmrs_referral_meeting_attendees`) and responsibility columns (`champion_user_id`, `transition_lead_user_id`).
9. Verify rewrite version **1.2.7**.
10. Verify staff portal routes.
11. Verify roles and capabilities.
12. Verify SMTP / `wp_mail`.
13. Verify private documents on the target host.
14. Verify Management Dashboard.
15. Run post-upgrade smoke tests.

---

## 11. Rollback

1. Restore previous plugin ZIP.
2. Restore database backup taken before upgrade (**preferred** when the schema advanced, e.g. `2.28.0` → `2.29.0`).
3. Restore `uploads` / `jmrs-private` if files changed.
4. Clear any page/object cache; refresh permalinks if routes misbehave.
5. Confirm `jmrs_db_version` matches the restored codebase expectations.

A code-only rollback after a completed DB migration does **not** fully reverse schema changes. Prefer restoring the pre-upgrade database backup together with the previous plugin files.

Do **not** roll product files forward while leaving an incompatible schema without a matching backup.

---

## 12. Deactivation and uninstall

| Action | Effect |
| --- | --- |
| Deactivate | Leaves data, roles, settings; flushes rewrites |
| Delete plugin (default) | Removes plugin files; **keeps** tables and private files; removes JM roles/caps |
| Opt-in wipe | Only if `JMRS_DELETE_DATA_ON_UNINSTALL` is `true` in `wp-config.php` on a disposable site |

Default uninstall **preserves** operational data. The opt-in destructive constant is an administrative/development operation — **not** the supported production retention or erasure workflow. Archive and approved retention processes are the supported production path. Complete purge coverage of every current schema table should be reviewed separately before relying on wipe behaviour.

See `docs/BACKUP_AND_RECOVERY.md` and `docs/DATA_RETENTION_POLICY.md`.

---

## Related documents

- `docs/RELEASE_CHECKLIST.md`
- `docs/RELEASE_NOTES_v1.5.0.md`
- `docs/ADMINISTRATOR_GUIDE.md`
- `docs/TROUBLESHOOTING.md`
- `docs/KNOWN_LIMITATIONS.md`
