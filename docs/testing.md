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
recovery, media capability wiring, and bundled PHP and JavaScript localization
for `en_US` and `zh_CN`. Do not describe a run that did not use WordPress as an
integration test.

## Translation Regeneration

The repository ships `universal-markdown-editor.pot`,
`universal-markdown-editor-zh_CN.po`,
`universal-markdown-editor-zh_CN.mo`, and
`universal-markdown-editor-zh_CN-ume-admin.json`. English remains the source
language. The integration suite asserts that the POT includes PHP and
JavaScript source strings, that `zh_CN` PHP translations load, that
user-visible REST errors use the plugin text domain, and that the
`ume-admin` script receives Chinese Jed data.

After changing user-visible PHP or JavaScript strings, regenerate the
translation files from the repository root:

```sh
PLUGIN_DIR=wp-content/plugins/universal-markdown-editor-for-wordpress

npx wp-env run cli wp i18n make-pot \
  "$PLUGIN_DIR" \
  "$PLUGIN_DIR/languages/universal-markdown-editor.pot" \
  --domain=universal-markdown-editor \
  --slug=universal-markdown-editor \
  --exclude=node_modules,vendor,dist,tests

npx wp-env run cli wp i18n make-mo "$PLUGIN_DIR/languages"

npx wp-env run cli wp i18n make-json \
  "$PLUGIN_DIR/languages/universal-markdown-editor-zh_CN.po" \
  "$PLUGIN_DIR/languages" \
  --domain=universal-markdown-editor \
  --pretty-print
```

Update the `zh_CN` PO translations before compiling. WP-CLI names generated
JavaScript JSON files from the source path and may use a `messages` domain.
This project packages the supported stable handle file instead:
`universal-markdown-editor-zh_CN-ume-admin.json`. Normalize the generated JSON
to that filename and use `universal-markdown-editor` for both the top-level
`domain` and `locale_data` keys. Remove superseded generated JSON files, rerun
`npm run test:integration`, and confirm both English and Simplified Chinese
states. Do not commit incomplete or empty translation artifacts; the release
build rejects them.

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

The release build keeps `src/autoload.php` unscoped. It contains only the
plugin bootstrap and no Composer dependency references; keeping its
`ABSPATH` guard outside PHP-Scoper's namespace wrapper also lets repository
checks inspect the guard in the exact packaged file. The build fails if that
guard is absent. The packaged root includes a generated production
`composer.json` describing the plugin and runtime requirements. It is written
from reviewed source metadata, excludes development dependencies and scripts,
converts exact production pins to compatible Caret constraints so the
publishable manifest passes strict validation, and the build validates it with
Composer. The source `composer.json` and `composer.lock` retain their exact
pins. `composer.lock` remains excluded from the release.

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
tracks the initial 13 warnings. The disposition is recorded below rather than
treated as an inert allowlist. Exact, local PHPCS comments are used only for
the database and logging cases that are required by the accepted storage
contract; the workflow does not suppress warning categories, files, or finding
codes globally.

| Warning | Occurrences | Disposition | Verification |
| --- | ---: | --- | --- |
| `missing_composer_json_file` | 1 | The release build now generates a minimal production `composer.json` and excludes the lockfile. | JavaScript metadata tests, Composer strict validation during the release build, and packaged Plugin Check. |
| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | 3 | Retained with a separate precise rationale at each call. The two option deletes require compare-and-delete semantics for per-post lock ownership; the posts update restores exact modification timestamps after failed-save compensation. | Storage integration cases for expired-lock recovery, lock release, injected failure, restoration, and unchanged modified time. |
| `WordPress.PHP.DevelopmentFunctions.error_log_error_log` | 1 | Retained with a precise rationale because silent save failure violates the operational contract. `SaveDiagnostics` emits only post ID, a validated stage, and restore status. | PHP unit test proves malformed stages cannot inject private Markdown into the diagnostic message, plus failed-save integration coverage. |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | 5 | The entrypoint compatibility and autoloader work now runs inside a static IIFE, so no plugin-global variables are introduced. | PHP subprocess tests cover unsupported and missing-dependency notices and assert the variables do not leak into `$GLOBALS`; Plugin Check covers packaging. |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | 3 | The unreleased public Hooks now use `universal_markdown_editor_`, as recorded in ADR 0002. Legacy short aliases are intentionally not emitted. | Plugin smoke integration asserts full-prefix Hooks fire and short Hooks do not; Plugin Check covers packaging. |

The final PR report must link the exact successful Plugin Check run, source
commit, ZIP SHA-256, actual WordPress/PHP runtime, and checker/action versions.
Those values are recorded after the source commit is immutable and therefore
are not embedded back into the source tree. Remaining manual acceptance items
in this document are separate from warning disposition.

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
