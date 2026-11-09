# ADR 0002: Use the Full Plugin Prefix for Public Hooks

- Status: Accepted for the unreleased v0.1.0 MVP through Issue #26
- Date: 2026-11-09
- Related: #26
- Supersedes: the `ume_` Hook prefix planning statement in `docs/architecture.md`

## Background

ADR 0001 established the plugin architecture before the first public release.
It proposed `ume_` as the prefix for JavaScript handles, REST identifiers, and
WordPress Hooks. The official Plugin Check correctly reports the three Hook
names beginning with `ume_` because WordPress.org expects a sufficiently
distinct plugin prefix.

The plugin has not created a formal Release and no released consumer contract
depends on these Hooks. Retaining aliases for the short names would continue
the naming conflict and expand an unreleased interface solely for compatibility
with code that cannot yet have a supported installation.

## Decision

Use the full `universal_markdown_editor_` prefix for public WordPress Hooks:

- `universal_markdown_editor_loaded`
- `universal_markdown_editor_activated`
- `universal_markdown_editor_deactivated`

Do not register aliases for `ume_loaded`, `ume_activated`, or
`ume_deactivated`. Keep the existing private `_ume_*` post-meta names because
they are persisted storage identifiers, not Hook names, and changing them would
require a separate data-contract decision and migration.

## Alternatives and Rejection Rationale

### Keep `ume_` and suppress the warning

Rejected. The short prefix is ambiguous in the WordPress Hook namespace. A
suppression would hide a public-interface finding without resolving the naming
problem.

### Register both names

Rejected for the unreleased MVP. It would keep the checker conflict and create
two public contracts before the first Release, increasing maintenance cost
without protecting any supported consumer.

### Rename persisted `_ume_*` metadata

Rejected for this Issue. Those names are private storage keys with consistency,
revision, and recovery implications. They do not cause the Hook warning and
must not be changed solely for scanner compliance.

## Consequences

- Hooks now use a unique, reviewable WordPress prefix.
- Pre-release code that used the short Hook names must migrate before v0.1.0.
- Activation, deactivation, and load behavior remain otherwise unchanged.
- The integration smoke test verifies the full-prefix Hooks fire and the short
  names do not.

## Verification

- Run plugin activation and deactivation integration coverage.
- Assert the loaded, activated, and deactivated Hooks fire with the full prefix.
- Assert legacy short Hooks are not emitted.
- Run the official packaged Plugin Check and retain its report and provenance.
