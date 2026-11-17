# ADR 0004: Preserve Gutenberg Content in Markdown Mode

- Status: Proposed
- Date: 2026-11-15
- Issue: #36
- Risk: High

## Context

The original MVP refused to import Gutenberg posts because a general HTML-to-Markdown conversion could silently lose block attributes and plugin data. Users now need to edit supported text in Markdown without losing unrelated blocks.

## Decision

1. Import only unstyled `core/paragraph` blocks with plain text and `core/heading` blocks with plain text and a valid heading level. Rich text, styles, attributes outside this subset, nested blocks, lists, media, embeds, dynamic blocks, and third-party blocks remain opaque.
2. Represent each opaque top-level block with an ordered `<!-- ume-preserved-block:N -->` marker. The marker is visible in Markdown source and cannot be deleted, duplicated, reordered, or embedded in a line; saves fail closed when validation fails.
3. The browser and server each perform the conservative import and must produce the same source before the Markdown workspace opens. A verified import opens automatically without a blocking confirmation. The browser never submits block HTML for persistence.
4. The server independently parses the current post baseline with WordPress `parse_blocks()` and extracts the exact top-level Gutenberg block source ranges from the original `post_content`. It substitutes only those server-derived source slices during preview and save, after validating the post's save baseline and the complete marker sequence. If source ranges cannot be matched one-to-one with parsed top-level blocks, import fails closed.
5. Store the opaque block map in private `_ume_preserved_blocks` post meta, register it for post revisions, and include it in repository snapshots and compensation restore. Existing managed Markdown posts without this meta are treated as having an empty map; marker-like source in such a post is rejected.
6. Preview remains read-only. Markdown segments use the existing server converter and sanitizer; opaque block serializations originate from the existing authorized post, not request HTML.

## Alternatives Considered

- Converting all serialized/rendered HTML to Markdown: rejected because block semantics and attributes cannot be recovered generically.
- Trusting serialized HTML submitted by the browser: rejected because a marker must never become a way to inject arbitrary persisted HTML.
- Silently dropping a block when its marker is missing: rejected because this would recreate the original data-loss risk.
- Replacing all blocks with a custom Markdown block: rejected because it changes Gutenberg's content model and is not required for this compatibility layer.

## Consequences and Limits

- Opening a verified Gutenberg import automatically opens the Markdown workspace. Supported paragraphs and headings become editable Markdown; every other block stays represented by a visible marker and can only be preserved, not edited, in Markdown mode.
- Markers cannot be removed to intentionally discard a block. Users must return to Gutenberg to make that edit.
- Opaque top-level Gutenberg block delimiters, attributes, nested content, and inner markup are retained as their exact original source slices. WordPress may still normalize content through the site's save filters; integration verification must confirm the actual stored `post_content` for supported environments.
- The mapping is private post meta and follows normal WordPress revision, backup, and restore behavior. Sites with revisions disabled retain the existing reduced recovery guarantee.
- Any expansion of the supported block subset requires paired browser/server tests and explicit review of no-loss behavior.

## Verification

The implementation adds WordPress integration coverage for mixed supported and opaque blocks, ordering, read-only open/preview, missing/duplicate/reordered markers, no-write rejection, persisted mappings, and revision recovery. Local integration verification remains required before merge.
