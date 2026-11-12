# ADR 0003: Render Markdown mode inside the Gutenberg editor page

- Status: Accepted through Issue #32
- Date: 2026-11-12
- Related: #30, #31, #32
- Extends: [ADR 0001](0001-markdown-editor-and-storage.md)
- Detailed proposal: [Issue #32](https://github.com/Weiki886/universal-markdown-editor/issues/32)

## Background

ADR 0001 selected an independent full-screen Modal workspace for the first
Markdown editor release. That kept the plugin away from Gutenberg block state
and provided a simple security boundary, but it also hid the WordPress article
header, document settings, and publishing controls while the author wrote
Markdown.

The plugin already owns the Markdown source and uses a dedicated REST save
path. The editor presentation can therefore change without creating a new
content model or synchronizing Markdown with individual Gutenberg blocks.

## Decision

1. Keep `registerPlugin` and the existing plugin sidebar as the entry point.
2. When a post is opened for Markdown editing, resolve a supported Gutenberg
   content host from the current editor DOM.
3. When a host is available, render the existing workspace through
   `@wordpress/element`'s `createPortal` into that host and mark the host while
   the inline surface is active.
4. Keep the WordPress editor header and sidebar visible; the portal replaces
   only the central content region.
5. Preserve the current full-screen Modal as a runtime fallback when no
   supported host can be resolved.
6. Keep nested link and confirmation dialogs on the existing WordPress Modal
   primitive.
7. Keep all REST, Markdown conversion, private metadata, save, revision,
   permission, and conflict behavior unchanged.
8. Restore the original editor canvas when the inline surface closes or its
   React tree unmounts.

The first supported host selector is
`.interface-interface-skeleton__content`, with `.editor-visual-editor` as a
secondary compatibility selector. Host activation is idempotent and restores
the previous host class state during cleanup.

## Alternatives and Rejection Rationale

### Keep the full-screen Modal

Rejected for this increment. It is safe but does not meet the product need for
a continuous article-editing context.

### Register a custom Markdown block

Rejected for this increment. It would add plugin block delimiters to
`post_content`, create a mixed Markdown/block content model, and require
additional compatibility and migration work.

### Use a separate administration page

Rejected. It would still move the author away from the Gutenberg post screen and
would not preserve the title and document settings in the same context.

### Maintain live two-way synchronization with Gutenberg

Rejected. Arbitrary blocks, shortcodes, and HTML cannot be converted to
Markdown losslessly. Markdown mode remains an explicit canvas mode.

## Consequences

### Positive

- The author keeps the WordPress article shell and publishing context visible.
- The existing Markdown workspace, preview, and save behavior remain reusable.
- Unsupported layouts retain a working full-screen fallback.
- No content or API migration is required.

### Costs and limitations

- Gutenberg does not provide a stable public slot for replacing the whole
  editor canvas, so the portal depends on documented-by-evidence DOM classes
  and must be covered by host detection and fallback behavior.
- The inline surface must hide and restore the underlying canvas carefully.
- Saving still follows the existing safe reload behavior; this ADR does not make
  the WordPress publish button a Markdown save path.
- Markdown and Gutenberg modes remain explicit rather than bidirectionally
  synchronized.

## Verification Requirements

- Unit-test host priority, missing-host behavior, and class restoration.
- Build against the declared WordPress 6.4 and 7.1 compatibility matrix.
- Render desktop and narrow editor layouts.
- Confirm the WordPress header and sidebar remain visible in inline mode.
- Confirm live preview, exit, dirty confirmation, and canvas restoration.
- Confirm the Modal path remains available when no supported host exists.
