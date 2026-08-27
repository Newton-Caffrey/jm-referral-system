=== J&M Referral System ===
Contributors: jmhealthcare
Tags: referrals, healthcare, care management, supported living, staff portal
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.0
Stable tag: 1.5.0
License: Proprietary (pending final licence decision)
License URI: https://example.com

Referral and care-management plugin for J&M Healthcare — intake through care commencement, with role-based access and private documents.

== Description ==

JM Referral System (JMRS) helps staff manage healthcare referrals from intake through the acquisition pipeline (interest, assessment, package cost, local authority decision, transition, care commencement) and ongoing Supported Living / domiciliary operations.

JMRS supports role-based access, audit activity, retention workflows, and private-document handling. Hosting TLS, backups, SMTP configuration, and web-server document protection remain the operator's responsibility. This plugin does not itself certify GDPR, CQC, NHS, or medical-device compliance.

== Installation ==

1. Upload the release ZIP so the plugin folder is exactly `jm-referral-system` (must include `vendor/autoload.php`).
2. Activate the plugin through the WordPress Plugins screen.
3. Configure SMTP / `wp_mail`, then complete Settings (public form and staff portal as needed).
4. Confirm database option `jmrs_db_version` is `2.29.0` after activation or upgrade.

Full steps: see `docs/INSTALLATION_GUIDE.md` in the package.

== Upgrade Notice ==

= 1.5.0 =

Upgrade from product 1.4.0. Back up the site, database, and current plugin directory before replacing files. Do not uninstall to upgrade. Database migrates additively from `2.28.0` to `2.29.0` (meetings, attendees, responsibility columns). Portal rewrite becomes `1.2.7`. Verify SMTP, private documents, and Management Dashboard after upgrade. Prefer restoring the pre-upgrade database backup if rolling back after migration.

== Changelog ==

= 1.5.0 =

Feature release from v1.4.0. Cumulative Phase 4B–4I UAT accepted as regression evidence; release-preparation UAT passed 2026-08-27. Full duplicate lifecycle replay not performed. Final staging smoke required before production.

* Meetings, internal attendees, and external participants
* Owner, Champion, and Transition Lead responsibilities
* Management Operations dashboard metrics and UI polish
* Assessment, Package Costing, LA decision, and care-commencement hardening
* Database schema `2.29.0` (additive upgrade from production `2.28.0`)
* Portal rewrite `1.2.7`

= 1.4.0 =

Historical production release: Management Dashboard presentation over real JMRS data. Database remained `2.28.0`. Portal rewrite `1.2.2`.
