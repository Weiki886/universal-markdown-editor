=== Universal Markdown Editor ===
Contributors: weiki
Tags: markdown, gutenberg, editor, writing, media
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit posts in Markdown with a Gutenberg workspace, server-rendered preview, formatting tools, and media library support.

== Description ==

Universal Markdown Editor adds a Markdown workspace to the Gutenberg post editor. It is designed for writers who prefer Markdown source while keeping saved post content available to WordPress themes as standard HTML.

Write WordPress posts in Markdown from the Gutenberg editor. Review a server-rendered preview, use the formatting toolbar, and insert media from the WordPress media library. The plugin keeps Markdown source in private post metadata and saves standard HTML to post_content.

= Features =

* Edit Markdown source and review a server-rendered HTML preview.
* Insert common Markdown syntax with a selection-aware toolbar.
* Choose or upload images through the WordPress media library.
* Store Markdown source separately from the standard HTML in post_content.
* Keep saved HTML readable after the plugin is deactivated.
* Check editing permissions and sanitize converted HTML on the server.
* Follow the WordPress site language with bundled English and Simplified Chinese translations.

This pre-release plugin is intended for the Gutenberg post editor. It does not support the Classic Editor, arbitrary Gutenberg block conversion, or theme-specific shortcodes. Review the known limitations and testing status before using it on a site.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin, or install it from the WordPress Plugin Directory after approval.
2. Activate Universal Markdown Editor from the Plugins screen.
3. Open a post in the Gutenberg editor and choose the Markdown workspace.
4. Review the source and preview before saving.

Back up the site before first enabling the plugin on existing content. Test installation and upgrades on a disposable staging site before using this pre-release build.

== Frequently Asked Questions ==

= Does this replace Gutenberg? =

No. It adds a Markdown workspace to the Gutenberg post editor. It does not replace every Gutenberg block or support the Classic Editor.

= Does it require a special theme? =

No theme-specific integration is intended. The plugin saves converted HTML in the standard post_content field. Theme rendering should still be checked on the target site.

= What happens to existing post content? =

Opening a post does not change it. Plain-text content can be imported after confirmation. Content containing blocks, HTML, or shortcodes that cannot be converted losslessly is left unchanged unless the user chooses to start with empty Markdown.

= Is Markdown source public? =

Markdown source is stored in post metadata and access to editor sessions is permission-checked. The public post content remains the converted HTML in post_content.

= What happens when the plugin is deactivated or uninstalled? =

Deactivation leaves the saved HTML in post_content, so themes can continue to display it. Uninstallation does not delete post content or Markdown metadata by default.

== Screenshots ==

Screenshots will be added after the editor has been verified in a clean WordPress installation. No screenshots are included in this pre-release readme yet.

== Changelog ==

= 0.1.0 =

* Initial pre-release candidate with a Gutenberg Markdown workspace, server-rendered preview, formatting toolbar, and media library insertion.
* Store editable Markdown source separately from sanitized standard post HTML.
* Add permission checks, save conflict handling, revision snapshots, and failed-save recovery.

== Upgrade Notice ==

= 0.1.0 =

This is a pre-release candidate. Back up your site and test on a disposable WordPress installation before use.
