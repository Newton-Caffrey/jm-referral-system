# Installation Guide — JM Referral System

Professional installation and go-live guide for WordPress hosts.

**Product version:** 1.4.0
**Database schema:** 2.28.0
**Portal rewrite:** 1.2.2

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

- Runs database migrations (`jmrs_db_version` → `2.28.0`)
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

1. Backup DB + uploads (including `jmrs-private`).
2. Deactivate plugin (optional but safer on some hosts).
3. Replace plugin files with the new release ZIP (keep `vendor/` included).
4. Activate / load admin so `Migrator::maybe_migrate()` runs.
5. Smoke-test dashboard, one referral view, document download, public form (if used), portal (if used).

Schema bumps only when `Migrator::DB_VERSION` changes. Product version and DB version are independent.

---

## 11. Rollback

1. Restore previous plugin ZIP.
2. Restore database backup taken before upgrade.
3. Restore `uploads` / `jmrs-private` if files changed.
4. Clear any page/object cache.
5. Confirm `jmrs_db_version` matches the restored codebase expectations.

Do **not** roll product files forward while leaving an incompatible schema without a matching backup.

---

## 12. Deactivation and uninstall

| Action | Effect |
| --- | --- |
| Deactivate | Leaves data, roles, settings; flushes rewrites |
| Delete plugin (default) | Removes plugin files; **keeps** tables and private files; removes JM roles/caps |
| Wipe | Only if `JMRS_DELETE_DATA_ON_UNINSTALL` is `true` in `wp-config.php` on a disposable site |

See `docs/BACKUP_AND_RECOVERY.md` and `docs/DATA_RETENTION_POLICY.md`.

---

## Related documents

- `docs/RELEASE_CHECKLIST.md`
- `docs/ADMINISTRATOR_GUIDE.md`
- `docs/TROUBLESHOOTING.md`
- `docs/KNOWN_LIMITATIONS.md`
