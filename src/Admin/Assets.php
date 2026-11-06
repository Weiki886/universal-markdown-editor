<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Admin;

final class Assets
{
    private const HANDLE = 'ume-admin';
    private const EDITOR_HOOKS = ['post.php', 'post-new.php'];

    public static function enqueue(string $hookSuffix): void
    {
        $screen = get_current_screen();

        if (!self::shouldEnqueue($hookSuffix, is_object($screen) ? $screen : null)) {
            return;
        }

        $assetFile = UME_DIR . 'build/index.asset.php';

        if (!is_readable($assetFile)) {
            return;
        }

        $canUploadMedia = current_user_can('upload_files');
        if ($canUploadMedia) {
            wp_enqueue_media();
        }

        $asset = require $assetFile;
        $baseUrl = plugin_dir_url(UME_FILE) . 'build/';

        wp_enqueue_script(
            self::HANDLE,
            $baseUrl . 'index.js',
            isset($asset['dependencies']) && is_array($asset['dependencies']) ? $asset['dependencies'] : [],
            isset($asset['version']) ? (string) $asset['version'] : UME_VERSION,
            true
        );

        wp_enqueue_style(
            self::HANDLE,
            $baseUrl . 'index.css',
            [],
            isset($asset['version']) ? (string) $asset['version'] : UME_VERSION
        );

        wp_localize_script(
            self::HANDLE,
            'umeSettings',
            [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'canUploadMedia' => $canUploadMedia,
            ]
        );
    }

    public static function shouldEnqueue(string $hookSuffix, ?object $screen): bool
    {
        if (!in_array($hookSuffix, self::EDITOR_HOOKS, true)) {
            return false;
        }

        if ($screen === null) {
            return false;
        }

        return ($screen->base ?? null) === 'post'
            && ($screen->post_type ?? null) === 'post';
    }
}
