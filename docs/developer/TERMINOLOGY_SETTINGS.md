# Terminology Settings

Phase **5A.2**. Display labels for client-facing wording. Technical keys stay fixed.

## Source of truth

`JMReferral\Settings\TerminologySettings`

| Option | Purpose |
| --- | --- |
| `jmrs_terminology_settings` | Label map |
| `jmrs_terminology_settings_schema` | Schema version `1` |

## Fields (defaults)

| Key | Default |
| --- | --- |
| `referral_singular` / `referral_plural` | Referral / Referrals |
| `client_singular` / `client_plural` | Client / Clients |
| `local_authority_singular` / `local_authority_plural` | Local Authority / Local Authorities |
| `commissioner_singular` / `commissioner_plural` | Commissioner / Commissioners |
| `service_singular` / `service_plural` | Service / Services |

## Boundary

Terminology **must not** change database values, enums, pipeline slugs, routes, capabilities, activity keys, assessment outcomes, package states, LA decision values, or care-setting keys.

Example: stored stage remains `awaiting_la_decision`; a heading may say “Awaiting Council Decision” only where display copy is composed safely.

## Safety

- Plain text, max 60 characters
- Reject arrays / markup / scripts
- Escape on output
- Blank values fall back to defaults

## Propagation (5A.2)

Applied to clear generic labels only (portal nav, admin menu, referrals list, services list, selected public intake labels, selected management dashboard headings). Domain-specific phrases and internal keys remain fixed.

See also: [`MODULE_SETTINGS.md`](MODULE_SETTINGS.md), [`ORGANISATION_SETTINGS.md`](ORGANISATION_SETTINGS.md).
