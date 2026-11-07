# Universal Markdown Editor for WordPress

A universal, theme-independent Markdown workspace for WordPress posts. The
editor keeps the Markdown source private while storing safe, standard
HTML in `post_content`, so the front end and native WordPress workflows remain
usable.

> **Project status:** the Gutenberg Markdown workspace, server-rendered preview,
> dual Markdown/HTML storage, formatting toolbar, and WordPress media insertion
> are under active development. The plugin is
> not yet ready for production use; follow the linked Issues and CI status.

## Quick Start

Requirements:

- Node.js 22.22.2 or newer and npm 10 or newer
- Docker with a running daemon
- No host PHP or Composer installation is required

```sh
npm ci
./bin/composer install
npm run check
npm run env:start
npm run test:integration
```

The local site is available at <http://localhost:8888/wp-admin> with the
`wp-env` default credentials: username `admin`, password `password`.

`npm run check` runs the JavaScript lint, production asset build, PHP unit
suite, and dependency license audit. `npm run test:integration` starts the
pinned WordPress environment and verifies plugin activation, editor asset
gating, server-side preview, private Markdown storage, permissions, revisions,
conflict handling, failed-save recovery, and media capability wiring.

## Development environment

The local and CI integration baseline is pinned in `.wp-env.json` to WordPress
7.1.4 and PHP 8.1. The minimum supported versions remain WordPress 6.4 and PHP
8.1 as declared by the plugin header. Issue #8 owns the full clean-environment
compatibility and release matrix.

Useful commands:

| Task | Command |
| --- | --- |
| Start WordPress | `npm run env:start` |
| Stop WordPress | `npm run env:stop` |
| Destroy local WordPress data | `npm run env:destroy` |
| Build development assets | `npm run build` |
| Run PHP unit tests | `npm run test:php` |
| Run WordPress integration smoke test | `npm run test:integration` |
| Build the installable release directory | `npm run build:release` |

`npm run build:release` writes the plugin to
`dist/universal-markdown-editor/`, installs production Composer dependencies,
scopes their namespaces with the `UME_Vendor` prefix, regenerates the autoloader,
and verifies the isolated runtime on PHP 8.1.

## Install a Development Build

This project has not published an official release. For evaluation only, a
successful GitHub Actions run provides a short-lived development artifact:

1. Open the successful `Verify plugin` run for the branch or pull request.
2. Download `universal-markdown-editor-development-build` and extract it.
3. Verify the `.zip.sha256` file with `sha256sum -c <archive>.sha256` on Linux,
   or `shasum -a 256 -c <archive>.sha256` on macOS.
4. Confirm the `.zip.source` file matches the commit you intend to test.
5. In a staging WordPress site, open **Plugins → Add New Plugin → Upload
   Plugin**, select the ZIP, install it, and activate it.

The ZIP includes `BUILD-INFO.txt` with its plugin version and full source commit.
Do not use this development artifact on a production site. A green static check
does not replace WordPress integration, theme, browser, or upgrade verification.

## Editing and Existing Posts

The plugin adds a Markdown workspace to the Gutenberg post editor; it does not
replace the editor for every post type and does not support the Classic Editor.
Use the Markdown editor for posts, review the server-rendered preview, then save.
The front end continues to use standard HTML stored in `post_content`.

Opening an unmanaged post does not change it. For plain text content, the editor
asks whether to import the current text as Markdown, start empty, or cancel. For
content containing blocks, HTML, or shortcodes that cannot be converted
losslessly, it warns that the original will remain unchanged and offers cancel
or starting with empty Markdown. Save only after reviewing the source and
preview. Back up the site before first enabling the plugin on existing content.

## Upgrade, Deactivation, and Recovery

- Before replacing a development build, export or back up the database and keep
  the `.zip.source` record for the build being tested.
- WordPress revisions are used to preserve the pre-save and saved post state
  when revisions are enabled. If revisions are disabled, the editor displays a
  warning that historical recovery is limited.
- Deactivation does not remove generated HTML from `post_content`; themes keep
  rendering that standard content. The plugin's Markdown source remains in
  post metadata.
- Uninstallation does not delete post content or Markdown metadata by default.
- To roll back code, deactivate and replace the plugin with the previous
  verified ZIP. If content needs recovery, use the post's WordPress Revisions
  screen or restore a known-good database backup. Test database restoration
  before relying on it; the plugin does not automate backup restoration.

See [SECURITY.md](SECURITY.md) for vulnerability reporting and [Testing](docs/testing.md)
for verification scope and known gaps.

## Safety and data boundaries

The editor reads Markdown sessions only for users who can edit the requested
post. Markdown source and consistency metadata are stored in private post meta;
`post_content` contains server-converted and sanitized standard HTML. Saving
checks an editing baseline, preserves revisions when enabled, verifies the
stored values, and attempts snapshot-based compensation if a write fails.
Deactivation leaves post content intact. These features are still under active
development and must pass the WordPress integration suite before production
use. Do not store secrets or production content in issues, pull requests, logs,
fixtures, or test environments.

## Documentation

- [Architecture and data contracts](docs/architecture.md)
- [ADR 0001: Markdown workspace and dual storage](docs/adr/0001-markdown-editor-and-storage.md)
- [Dependency and license inventory](docs/dependency-licenses.md)
- [Engineering rules](AGENTS.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
