# Organisation & Branding Settings

Phase **5A.1** foundation for installing the same JMRS codebase for different care providers without hard-coded JM Healthcare identity in the operational UI.

Production-facing product version remains **1.5.0** until a later release. Development branch: `develop/1.6.0`.

## Source of truth

| Layer | Responsibility |
| --- | --- |
| `JMReferral\Settings\OrganisationSettings` | Canonical getters, sanitised update, defaults, hex/logo validation |
| WordPress options | `jmrs_organisation_settings` (+ schema `jmrs_organisation_settings_schema` = `1`) |
| Presentation | Portal branding, public branding, management masthead, email sender name |

**Precedence**

1. Saved `jmrs_organisation_settings` (when the option exists)
2. Legacy `jmrs_staff_portal_settings` / `jmrs_public_referral_settings` field values (upgrade compatibility only)
3. Built-in JM Healthcare defaults (`JM Healthcare`, portal title, `#0b5f4b` / `#1a3a32`)

Templates and services must call `OrganisationSettings` (or `PortalSettings::branding()` / `PublicBranding::*`, which delegate to it). Do not scatter new `get_option('jmrs_organisation_settings')` reads in templates.

Saving Organisation & Branding **syncs mirrors** into portal/public options so older code paths stay coherent. Portal/Public Settings UIs no longer edit branding identity fields.

## What stays technical / unchanged

- `jmrs_*` option keys, capabilities, table names, route slugs, nonce keys
- `JMReferral\` namespace
- Plugin file / text domain / product header name (commercial rename is separate)
- Database schema **2.29.0** (no migration in 5A.1)
- Portal rewrite **1.2.7**
- WordPress site timezone and date/time formats remain authoritative (Organisation Settings shows timezone as informational only)

## Security

- Capability: `jmrs_manage_settings`
- Nonce on save
- Explicit allowlist in `OrganisationSettings::update()` (no raw `$_POST` persistence)
- Hex-only colours; http(s) website URLs; email validation; attachment must be an image
- Escaped output in Settings UI and consumers

## Audit logging

There is **no** system-level configuration audit log suitable for `organisation_settings_updated` / `branding_settings_updated`. Do **not** write these events into referral activity. Treat platform config audit as a later requirement.

## Explicitly not in 5A.1

- Email intake / Graph / Gmail / OAuth / IMAP / webhooks / Referral Inbox
- Local Authority directory (requires schema 2.30.0+)
- Module enable/disable switches
- Configuration export/import
