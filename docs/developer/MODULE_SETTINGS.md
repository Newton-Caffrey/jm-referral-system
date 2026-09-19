# Module Settings

Phase **5A.2**. Per-installation operational module switches.

## Source of truth

`JMReferral\Settings\ModuleSettings` (+ `ModuleGate` for denials)

| Option | Purpose |
| --- | --- |
| `jmrs_module_settings` | Enabled map |
| `jmrs_module_settings_schema` | Schema version `1` |

## Modules

| Key | Scope |
| --- | --- |
| `meetings` | Meeting records / attendees |
| `assessments` | Assessment scheduling & outcomes |
| `package_costing` | Package cost prepare/send |
| `la_decisions` | Local Authority decisions |
| `supported_living` | Homes, bedrooms, occupancy, Place Resident |
| `transition` | Transition planning & care commencement |
| `management_dashboard` | Management Dashboard portal |
| `reports` | Admin reports / CSV |

Email intake is **not** modelled in this phase.

## Defaults / upgrade

When `jmrs_module_settings` is absent, **all known modules default ON** (existing v1.5 / JM behaviour preserved).

## Dependency graph (enforced on save)

```text
assessments
    ↑
package_costing
    ↑
la_decisions
    ↑
transition  (includes care commencement)
```

Independent: `meetings`, `supported_living`, `management_dashboard`, `reports`.

Invalid combinations are rejected; previous valid configuration is kept.

## Disable semantics

- Hide normal navigation entries
- Hide / disable new-action controls (`can_*` returns false)
- Deny service-layer mutations (no activity written)
- Deny direct portal write routes / POSTs
- **Preserve** historical rows, pipeline history, and activity
- Do **not** DROP tables, delete rows, or auto-move pipeline stages

Historical read access remains available where practical (e.g. existing meeting list/detail GET).

## Management Dashboard / Reports

- Dashboard: module-specific ops blocks omitted when the related module is off; enabled-module metrics unchanged
- Reports: whole Reports menu/page gated by `reports`; Supported Living report sections omitted when `supported_living` is off

## Access

Configured under Settings with `jmrs_manage_settings`.

See also: [`TERMINOLOGY_SETTINGS.md`](TERMINOLOGY_SETTINGS.md).
