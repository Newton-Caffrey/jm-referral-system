# Packaging — Production ZIP

How to build the distributable plugin archive for WordPress.

**Lesson from v1.2.0:** the ZIP must contain the real plugin folder name `jm-referral-system`, not a versioned wrapper folder.

**Planned artifact (v1.5.0):** `jm-referral-system-1.5.0.zip`

Create the release ZIP only when packaging is explicitly requested after release evidence acceptance and final documentation updates.

---

## Correct archive structure

```text
jm-referral-system-1.5.0.zip
└── jm-referral-system/
    ├── jm-referral-system.php
    ├── uninstall.php
    ├── composer.json
    ├── readme.txt
    ├── README.md
    ├── CHANGELOG.md
    ├── LICENSE
    ├── SECURITY.md
    ├── src/
    ├── templates/
    ├── assets/
    ├── languages/
    ├── vendor/                (required — vendor/autoload.php is mandatory)
    └── docs/                  (optional lean operator subset; see exclusions)
```

WordPress expects:

```text
wp-content/plugins/jm-referral-system/jm-referral-system.php
```

### Do NOT package

- `jm-referral-system-V1.x.x/` or any renamed wrapper that is not exactly `jm-referral-system`
- nested duplicate copies of the plugin inside the ZIP

A wrong root folder causes WordPress to treat the upload as a **different plugin**, risking double installs or accidental deletion/uninstall of the live plugin (and its data) when “replacing”.

---

## Build steps

1. Prepare the **actual** development plugin folder (`jm-referral-system`), matching the signed-off build.
2. Ensure Composer autoload is present:

```bash
composer install --no-dev --optimize-autoloader
```

(With empty third-party `require` beyond `php`, the committed `vendor/` stub is sufficient.)

3. Copy to a clean staging folder named exactly `jm-referral-system`.
4. Remove excluded paths from the staging copy.
5. ZIP that folder (artifact name `jm-referral-system-1.5.0.zip`).
6. Record file count, ZIP size, and SHA-256; run malware/secret scan.
7. Verify archive root before upload.
8. Smoke on staging: clean install + upgrade from production **v1.4.0** / DB **2.28.0**.
9. **Never** run uninstall/delete merely to replace a working plugin if preservation of data is required.

### PowerShell example (staging copy)

```powershell
$src = "C:\path\to\jm-referral-system"
$stage = "C:\path\to\staging\jm-referral-system"
$dest = "C:\path\to\jm-referral-system-1.5.0.zip"

# Copy to a clean staging folder named exactly jm-referral-system
# Remove excluded paths from $stage, then:
Compress-Archive -Path $stage -DestinationPath $dest
```

---

## Must include

- `jm-referral-system.php`
- `uninstall.php`
- `src/`, `templates/`, `assets/`
- `vendor/` (**`vendor/autoload.php` mandatory**)
- `composer.json`
- `languages/`
- Root licence / readme files as shipped (`README.md`, `readme.txt`, `CHANGELOG.md`, `LICENSE`, `SECURITY.md`, …)

---

## Must exclude

- `.git/`, `.github/` (where not operationally required)
- IDE files (`.idea/`, `.vscode/`, …)
- OS metadata (`.DS_Store`, `Thumbs.db`)
- `_reference/`
- `docs/uat/`
- `docs/audits/`
- Screenshots / UAT evidence / PHI exports
- Database dumps (`*.sql`), logs, existing ZIP files
- Staging files, temporary files, local configuration, secrets
- `node_modules/`, `.env`, `uat-evidence/`

---

## Verify before upload

- [ ] Unzip → single top-level folder named exactly `jm-referral-system`
- [ ] `jm-referral-system/jm-referral-system.php` exists at that path
- [ ] `vendor/autoload.php` exists
- [ ] Plugin header Version matches intended release (`1.5.0`)
- [ ] `JMRS_VERSION` is `1.5.0`
- [ ] DB constant remains `2.29.0`; rewrite remains `1.2.7`
- [ ] Activates on a clean WordPress staging site
- [ ] Upgrade from production **v1.4.0** leaves `Migrator::DB_VERSION` at `2.29.0`
- [ ] Portal rewrite reaches `1.2.7` after upgrade when the stored option lags
- [ ] Chart.js / Google Fonts network behaviour understood for the target environment

---

## Upgrade vs replace

- Prefer WordPress **Update Plugin** / replace files in place for the same plugin slug.
- Do **not** delete the existing `jm-referral-system` plugin folder solely to install a ZIP that used a different folder name.
- Default uninstall preserves operational data; avoid uninstall when preserving production data. Opt-in wipe is not the supported retention workflow.

---

## Release ZIP timing

Create the final release ZIP only after Phase **4J.1** regression and this packaging checklist are complete, and only when the project owner explicitly requests packaging.
