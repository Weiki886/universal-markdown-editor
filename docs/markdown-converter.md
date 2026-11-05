# Markdown conversion core

Issue #4 implements converter version 1 as the reusable server-side boundary for
later preview and save flows. It does not register a REST route, load editor UI,
or write posts or metadata.

## Public API

```php
use UniversalMarkdownEditor\Markdown\ConversionException;
use UniversalMarkdownEditor\Markdown\Converter;

$converter = new Converter();

try {
    $html = $converter->convert($markdownSource);
} catch (ConversionException $exception) {
    $errorCode = $exception->errorCode();
}
```

`Converter::VERSION` is `1`. `convert()` accepts a UTF-8 string of at most
1 MiB (1,048,576 bytes) and returns a sanitized HTML string. Empty input returns
an empty string. The same source and converter version produce deterministic
output.

Errors are exceptions rather than partial HTML:

- `ume_invalid_source`: the source exceeds 1 MiB or is not valid UTF-8.
- `ume_conversion_failed`: parsing or sanitization failed. The previous exception
  is available for server-side diagnostics, but source and partial HTML are not
  included in the public exception message.

Constructor injection exists to isolate parser and WordPress boundaries in unit
tests. Preview and save services must use the default constructor so they share
the fixed production pipeline.

## Parsing and sanitization

The production parser is `league/commonmark` 2.10.7 with only CommonMark core,
Table, Strikethrough, and Autolink extensions. It does not enable the complete
GFM bundle or task lists. Raw HTML is escaped, unsafe links are disabled, AST
nesting is capped at 100, and delimiters per line are capped at 1,000.

`HtmlSanitizer` then applies the accepted WordPress tag/attribute whitelist with
`wp_kses`. WordPress's allowed protocols are used except `javascript`,
`vbscript`, `file`, and every `data` URL, which remain denied even if a site
filter tries to re-enable them. WordPress can neutralize an unsafe URL by
removing its scheme while retaining a harmless relative value; the security
contract is that no dangerous protocol survives, not that the attribute itself
must always disappear.

Only `language-*` classes with conservative parser-generated characters remain
on `code` elements. Arbitrary classes, event attributes, raw HTML elements,
`target`, and `rel` are not accepted. No target blank behavior or frontend
highlighting script is added.

## Verification

- `npm run test:php` runs the parser, golden fixture, boundary, and failure
  injection tests on PHP 8.1.
- `npm run test:integration` runs the complete pipeline with real WordPress
  `wp_kses`, protocol filters, and the HTML Tag Processor. It covers safe and
  malicious markup, dangerous URLs, deterministic output, and read-only
  database behavior.
- `npm run build:release` verifies the plugin converter and its scoped parser
  after production dependencies are moved into the `UME_Vendor` namespace.
  The WordPress sanitizer is verified separately by the integration suite.
