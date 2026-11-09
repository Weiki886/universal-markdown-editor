# Testing and Compatibility

This document separates automated results from compatibility work that still
requires a real WordPress installation or human acceptance.

## Local Checks

From the repository root, after `npm ci` and `./bin/composer install`:

```sh
npm run check:js
npm run test:js
npm run build
npm run test:php
npm run check:licenses:self-test
npm run check:licenses
npm run check:dependencies
```

`npm run test:integration` uses the pinned `wp-env` installation and requires
Docker. It verifies plugin activation, Markdown conversion and sanitization,
REST permissions, post storage, revisions, conflict handling, failed-save
recovery, and media capability wiring. Do not describe a run that did not use
WordPress as an integration test.

The CI workflow also builds the installable development package, scans tracked
and non-ignored source files with a pinned Gitleaks CLI, audits locked runtime
dependencies, and publishes a seven-day ZIP artifact with a source SHA and
SHA-256 sidecar. Composer's production graph is audited; npm's production graph
is audited with development dependencies omitted because npm packages are build
tools and are not shipped in the plugin ZIP. The full npm development-tool graph
currently reports known advisories, including one high-severity finding in the
build toolchain; those advisories are not represented as fixed by the runtime
audit. Review them before changing the build toolchain or preparing a formal
release. Credential scanning and dependency audits are automated checks, not
proof that the code is free from all vulnerabilities.

## Official Plugin Check on the ZIP

After the existing verification job passes, the `WordPress Plugin Check` job
downloads its development ZIP artifact, requires exactly one ZIP, verifies its
SHA-256 sidecar and source SHA against the workflow commit, and checks the
embedded `BUILD-INFO.txt` source SHA. It extracts a separate copy for scanning;
the original ZIP and sidecars remain in the development-build artifact.

The job uses the official `WordPress/plugin-check-action` v1.1.9, pinned to
`10857da14b6c2246d15402b3e69f777edcf8c12e`, on WordPress 7.1.4 / PHP 8.1.
Repository permissions remain read-only and automatic PR comments are disabled.
No checks, categories, files, or finding codes are filtered by our workflow.
The action's default non-experimental checks run: errors fail CI; warnings
remain in the report and must be reviewed and resolved or explicitly justified
before any directory submission. A green job does not mean there are no
warnings, nor does it guarantee acceptance by the WordPress.org review team.

Open the workflow run and download `plugin-check-results` for the checker
report, `plugin-check-provenance` for the run URL/source SHA/ZIP checksum, and
`universal-markdown-editor-development-build` for that exact ZIP and sidecars.
The action attempts to upload its report even on failure; a startup or tooling
failure may leave no report, in which case inspect the job logs rather than
recording a clean scan. Download evidence before its artifact retention expires
(the ZIP and provenance expire after seven days; the report follows the
action's default repository retention).

The action installs its own current `@wordpress/env` and Plugin Check versions;
they are not pinned by the action reference. Consult its environment and plugin
listing logs for the actual versions used. The pinned action and traceable ZIP
provide repeatable execution and attributable findings, not a hermetic scanner
snapshot. Run a fresh check on the exact submission candidate and retain its
report and environment versions. Plugin Check supplements the manual acceptance
record below; it cannot replace theme/browser checks, real screenshots, or
human review. This workflow does not submit or publish the plugin.

The initial run (`42573283901`) found two errors and 13 warnings. The focused
corrections protect the autoloader from direct access and separate conversion
exception construction from throwing; the original cause remains private and
the public error message stays fixed. Unit tests exercise the autoloader both
outside and inside a WordPress bootstrap, and check that parser/sanitizer
exception details never enter the public message. Unit and scoped-build smoke
test bootstraps define `ABSPATH` explicitly; they are not full WordPress
integration environments.

[Issue #26](https://github.com/Weiki886/universal-markdown-editor-for-wordpress/issues/26)
tracks all 13 warnings: missing Composer metadata, direct database calls,
operational logging, global-variable prefixes, and Hook prefixes. They remain
open until fixed or individually justified with evidence before submission.
Do not interpret an error-free report as disposition of those warnings.

## Declared Compatibility

The plugin header declares WordPress 6.4+ and PHP 8.1+. CI runs the WordPress
integration suite across WordPress 6.4.14 and 7.1.4, each with PHP 8.1, 8.2,
and 8.3. This is a bounded compatibility sample, not proof for every WordPress
maintenance release, PHP version, database, or hosting configuration. The
non-default theme and browser viewport/keyboard workflows still require manual
acceptance.

The plugin is intended for the Gutenberg post editor. The Classic Editor,
non-post content types, theme-specific shortcodes, and lossless conversion of
arbitrary block/HTML/shortcode content are outside the current MVP.

## Manual Acceptance Record

Before a formal release, record the exact source commit and ZIP SHA-256, then
verify in a clean disposable WordPress installation:

- Install and activate the ZIP; confirm there are no PHP warnings or fatal
  errors.
- Save and reload a new Markdown post; confirm the Markdown source and rendered
  standard HTML match.
- Open an existing plain-text post; cancel the import prompt and confirm the
  post is unchanged, then test import on a separate fixture.
- Open a block/HTML/shortcode post; confirm the lossless-conversion warning,
  cancel, and verify the original remains unchanged.
- Test an unauthorized account and an account without `upload_files`; verify
  preview/save and media controls are denied as appropriate.
- View a saved post in the current default WordPress theme and at least one
  independent third-party theme; compare rendered headings, links, images,
  lists, tables, and code blocks.
- Check editor operation at desktop and narrow-screen sizes, including keyboard
  selection and save failure recovery.
- Deactivate the plugin and verify front-end HTML remains readable; uninstall
  and verify content and Markdown metadata remain by default.
- Upgrade from the previous tested ZIP and rehearse code rollback and database
  restore using a disposable backup.

Record WordPress/PHP versions, theme names and versions, browser/viewport,
source commit, artifact filename, ZIP SHA-256, tester, date, outcomes, and any
unresolved issue. A release must remain blocked until required acceptance
evidence exists; CI status alone is not a human acceptance record.
