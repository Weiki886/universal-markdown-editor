<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor;

use UniversalMarkdownEditor\Admin\Assets;
use UniversalMarkdownEditor\Post\MarkdownRepository;
use UniversalMarkdownEditor\REST\MarkdownDocumentController;
use UniversalMarkdownEditor\REST\MarkdownPreviewController;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        add_action('init', [MarkdownRepository::class, 'registerMeta']);
        add_action('admin_enqueue_scripts', [Assets::class, 'enqueue']);
        add_action('rest_api_init', [MarkdownPreviewController::class, 'registerRoutes']);
        add_action('rest_api_init', [MarkdownDocumentController::class, 'registerRoutes']);
        register_activation_hook(UME_FILE, [Lifecycle::class, 'activate']);
        register_deactivation_hook(UME_FILE, [Lifecycle::class, 'deactivate']);

        do_action('universal_markdown_editor_loaded');
    }
}
