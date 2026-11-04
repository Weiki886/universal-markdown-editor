# Universal Markdown Editor for WordPress

A universal, theme-independent Markdown workspace for WordPress posts. The
planned editor keeps the Markdown source private while storing safe, standard
HTML in `post_content`, so the front end and native WordPress workflows remain
usable.

> **Project status:** early scaffold. The plugin can be installed and activated,
> but the Markdown editor is not implemented yet and this project is not ready
> for production use.

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
pinned WordPress environment and verifies plugin activation, lifecycle hooks,
version metadata, and admin asset gating.

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

## Safety and data boundaries

The current scaffold does not read, create, update, or delete posts or plugin
metadata. Activation and deactivation only register and fire namespaced
lifecycle hooks.

The accepted architecture requires server-side Markdown conversion, WordPress
HTML sanitization, per-post permission checks, and private post meta. Those
editor and persistence behaviors are not implemented in this issue. Do not
store secrets or production content in issues, pull requests, logs, fixtures,
or test environments.

## Documentation

- [Architecture and data contracts](docs/architecture.md)
- [ADR 0001: Markdown workspace and dual storage](docs/adr/0001-markdown-editor-and-storage.md)
- [Dependency and license inventory](docs/dependency-licenses.md)
- [Engineering rules](AGENTS.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
