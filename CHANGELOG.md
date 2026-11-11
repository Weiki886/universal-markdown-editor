# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Restore exact post modification timestamps after failed Markdown saves.

### Changed

- Refine the Markdown workspace with a compact icon toolbar, full-height
  aligned source and preview panels, internal scrolling, automatic preview
  updates, and persistent save and exit actions below the editing surface.

### Added

- Bundled English and Simplified Chinese localization for PHP messages and the
  editor interface, following the WordPress site language.
- WordPress integration compatibility coverage across the declared minimum and
  current project baseline on PHP 8.1, 8.2, and 8.3.
- Security and dependency audits, a source-traceable development ZIP with a
  SHA-256 sidecar, and installation, compatibility, recovery, and disclosure
  documentation.
- Markdown formatting toolbar with selection-aware formatting, safe link entry,
  and permission-gated WordPress media library insertion.
- Gutenberg Markdown workspace persistence with private source metadata,
  server-generated standard HTML, per-post REST permissions, conflict checks,
  revision snapshots, and failed-write compensation.
- WordPress plugin entry point, namespaced autoloader, lifecycle hooks, and
  compatibility checks.
- Gutenberg admin asset entry and post-editor-only asset gating.
- PHP unit tests, license auditing, PHP 8.1 syntax checks, release packaging,
  and a pinned WordPress integration environment.
- Dependency and license inventory.
- Reusable Markdown-to-HTML converter version 1 with fixed CommonMark
  extensions, golden fixtures, input limits, and controlled conversion errors.
- WordPress HTML/URL sanitization and real WordPress security integration tests.
- PHP 8.1-compatible PHPUnit object instantiation and patched build filesystem
  dependencies.
