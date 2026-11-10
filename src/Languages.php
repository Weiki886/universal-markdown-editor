<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor;

final class Languages
{
    public const DOMAIN = 'universal-markdown-editor';

    public static function load(): void
    {
        load_plugin_textdomain(
            self::DOMAIN,
            false,
            dirname(plugin_basename(UME_FILE)) . '/languages'
        );
    }
}
