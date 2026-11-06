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

## Declared Compatibility

The plugin header declares WordPress 6.4+ and PHP 8.1+. The current integration
environment is pinned to WordPress 7.1.4 and PHP 8.1. The minimum WordPress
version, other PHP versions, non-default themes, and browser viewport/keyboard
combinations are not yet covered by a complete automated matrix. A declaration
of minimum requirements is not evidence that every combination has been tested.

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
