# Packaging — Production ZIP

How to build the distributable plugin archive for WordPress.

**Lesson from v1.2.0:** the ZIP must contain the real plugin folder name `jm-referral-system`, not a versioned wrapper folder.

---

## Correct archive structure

```text
jm-referral-system.zip
└── jm-referral-system/
    ├── jm-referral-system.php
    ├── composer.json
    ├── composer.lock          (optional but useful)
    ├── src/
    ├── templates/
    ├── assets/
    ├── docs/
    ├── vendor/                (required for production when Composer autoload is used)
    ├── uninstall.php          (if present)
    ├── README.md
    ├── CHANGELOG.md
    └── …
```

WordPress expects:

```text
wp-content/plugins/jm-referral-system/jm-referral-system.php
```

### Do NOT package

- `jm-referral-system-V1.3.0/`
- `jm-referral-system-V1.3.0.1/`
- any renamed/wrapper directory that is not exactly `jm-referral-system`
- nested duplicate copies of the plugin inside the ZIP

A wrong root folder causes WordPress to treat the upload as a **different plugin**, risking double installs or accidental deletion/uninstall of the live plugin (and its data) when “replacing”.

---

## Build steps

1. Prepare the **actual** development plugin folder (`jm-referral-system`), matching the release tag / signed-off build.
2. Ensure dependencies/autoload are present:

```bash
composer install --no-dev --optimize-autoloader
```

3. ZIP that actual `jm-referral-system` folder (folder name must remain `jm-referral-system`).
4. Verify archive root before upload.
5. Upload / update carefully on staging first, then production.
6. Activate / verify DB version and smoke tests.
7. **Never** run uninstall/delete merely to replace a working plugin if preservation of data is required.

### PowerShell example (staging copy)

```powershell
$src = "C:\path\to\jm-referral-system"
$stage = "C:\path\to\staging\jm-referral-system"
$dest = "C:\path\to\jm-referral-system-X.Y.Z.zip"

# Copy to a clean staging folder named exactly jm-referral-system
# Remove excluded paths from $stage, then:
Compress-Archive -Path $stage -DestinationPath $dest
```

Prefer copying to a staging directory, delete excluded paths, then zip the folder whose name is `jm-referral-system`.

---

## Must include

- `jm-referral-system.php`
- `src/`, `templates/`, `assets/`, `docs/`
- `vendor/` (runtime Composer autoload)
- `uninstall.php` (if present)
- `composer.json` / `composer.lock` (optional but useful)
- `README.md`, `CHANGELOG.md`, `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, `ROADMAP.md` (as applicable)

---

## Must exclude

- `.git/`, `.github/`
- `node_modules/`
- `.idea/`, `.vscode/`
- `.DS_Store`, `Thumbs.db`, `*.log`, `.env`
- `uat-evidence/` (and any local UAT screenshots/exports)
- Local dumps, personal notes, test fixtures with PHI
- Temporary CSV exports / backup copies inside the plugin tree
- Duplicate nested copies of the plugin

---

## Verify before upload

- [ ] Unzip → single top-level folder named exactly `jm-referral-system`
- [ ] `jm-referral-system/jm-referral-system.php` exists at that path
- [ ] `vendor/autoload.php` exists
- [ ] Plugin header Version matches intended release (`1.4.0`)
- [ ] `JMRS_VERSION` is `1.4.0`
- [ ] Activates on a clean WordPress staging site
- [ ] Upgrade from previous production leaves `Migrator::DB_VERSION` at `2.28.0` (no migration in 1.4.0)
- [ ] Fresh install also reaches current DB version with canonical pipeline stages seeded
- [ ] Portal rewrite reaches `1.2.2` after first load when upgrading from `1.2.1`
---

## Upgrade vs replace

- Prefer WordPress **Update Plugin** / replace files in place for the same plugin slug.
- Do **not** delete the existing `jm-referral-system` plugin folder solely to install a ZIP that used a different folder name.
- Uninstall hooks may remove data — avoid uninstall when preserving operational data.

---

## Release ZIP timing

Create the final release ZIP only after Phase 3 UAT sign-off and this release preparation is complete. Still do **not** create the ZIP until the project owner explicitly requests packaging.
