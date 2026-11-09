<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor;

final class Lifecycle
{
    public static function activate(): void
    {
        do_action('universal_markdown_editor_activated');
    }

    public static function deactivate(): void
    {
        do_action('universal_markdown_editor_deactivated');
    }
}
