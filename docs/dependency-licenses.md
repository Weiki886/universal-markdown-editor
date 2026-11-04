# Dependency and license inventory

This document records the dependency set locked by `composer.lock` and
`package-lock.json` for issue #3. It is an engineering audit, not a legal
opinion. Re-run the automated checks whenever either lockfile changes.

## Reproduction

The dependency resolution is reproducible from the committed lockfiles:

```sh
./bin/composer install
npm ci
npm run check:licenses:self-test
npm run check:licenses
```

The project uses Composer 2.8.12 through `./bin/composer` when Composer is not
installed locally. The frontend toolchain is pinned to Node.js 22 LTS or newer
and npm 10 or newer.

## Composer production dependencies

The production graph contains eight packages. These packages are installed
with `--no-dev`, scoped by PHP-Scoper, and included in the release artifact.

| Package | Locked version | License |
| --- | --- | --- |
| `dflydev/dot-access-data` | `3.0.3` | MIT |
| `league/commonmark` | `2.10.7` | BSD-3-Clause |
| `league/config` | `1.2.0` | BSD-3-Clause |
| `nette/schema` | `1.4.0` | BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only |
| `nette/utils` | `4.0.11` | BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only |
| `psr/event-dispatcher` | `1.0.0` | MIT |
| `symfony/deprecation-contracts` | `3.8.0` | MIT |
| `symfony/polyfill-php80` | `1.43.0` | MIT |

`nette/utils` is constrained to `4.0.11` because the next resolved release,
`4.1.6`, requires PHP 8.2 or newer. The plugin declares and tests PHP 8.1
compatibility, so the PHP 8.2-only release cannot enter the production graph.

## Composer development dependencies

The development graph contains 50 packages, including:

| Direct package | Locked version | License |
| --- | --- | --- |
| `humbug/php-scoper` | `0.18.20` | MIT |
| `phpunit/phpunit` | `9.6.38` | BSD-3-Clause |
| `yoast/phpunit-polyfills` | `4.0.0` | BSD-3-Clause |

The remaining 47 packages are transitive test and build dependencies. The
license checker validates every package in `composer.lock`, not only the direct
entries listed above. Development dependencies are not copied into the release
artifact.

## npm development dependencies

`package-lock.json` contains 1,598 locked package entries including transitive
dependencies:

| Direct package | Locked version | License |
| --- | --- | --- |
| `@wordpress/env` | `12.1.0` | GPL-2.0-or-later |
| `@wordpress/scripts` | `37.1.0` | GPL-2.0-or-later |

These packages and their transitive graph are development-only and are not
included in the release artifact.

Three locked packages omit license metadata. The checker accepts them only
when the exact locked version, license file path, and SHA-256 digest match:

| Package | License | Evidence file | SHA-256 |
| --- | --- | --- | --- |
| `@php-wasm/util@3.1.62` | GPL-2.0-only | `node_modules/@php-wasm/util/LICENSE` | `8177f97513213526df2cf6184d8ff986c675afb514d4e68a404010521b880643` |
| `@wp-playground/blueprints@3.1.62` | GPL-2.0-only | `node_modules/@wp-playground/blueprints/LICENSE` | `8177f97513213526df2cf6184d8ff986c675afb514d4e68a404010521b880643` |
| `svg-tags@1.0.0` | MIT | `node_modules/svg-tags/LICENSE` | `4fa26a349d96c6bf268296011d84a70d18a1e85dfbef9a3c3f785c97e28d1f5d` |

## Automated policy

`scripts/check-licenses.mjs` allows a limited set of permissive and
WordPress-compatible copyleft expressions. It fails on unknown license
expressions, missing Composer metadata, missing npm evidence, or changed
license-file hashes. The self-test verifies representative accepted and
rejected expressions before the lockfiles are audited.
