# Packaging — Production ZIP (v1.2.0)

How to build the distributable plugin archive.

---

## Build steps

1. Ensure working tree matches the release tag (`v1.2.0`).
2. From the plugin root:

```bash
composer install --no-dev --optimize-autoloader
```

3. Create a ZIP whose **root folder** is `jm-referral-system/` (WordPress expects this layout).

### PowerShell example

```powershell
$src = "C:\path\to\jm-referral-system"
$dest = "C:\path\to\jm-referral-system-1.2.0.zip"
# Prefer Compress-Archive after copying to a clean staging folder that excludes junk
```

Prefer copying to a staging directory, delete excluded paths, then zip.

---

## Must include

- `jm-referral-system.php`
- `src/`, `templates/`, `assets/`, `docs/`
- `vendor/` (runtime Composer autoload)
- `uninstall.php` (if present)
- `composer.json` / `composer.lock` (optional but useful)
- `README.md`, `CHANGELOG.md`, `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, `ROADMAP.md`

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

- [ ] Unzip → single top-level `jm-referral-system` folder
- [ ] `vendor/autoload.php` exists
- [ ] Plugin header Version `1.2.0`
- [ ] `JMRS_VERSION` is `1.2.0`
- [ ] Activates on a clean WordPress staging site
- [ ] `jmrs_db_version` reaches `2.21.0`
