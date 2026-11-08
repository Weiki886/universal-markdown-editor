<?php
/**
 * Plugin Name: Universal Markdown Editor
 * Description: Edit WordPress posts from a focused Markdown source workspace.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: universal-markdown-editor
 */

declare(strict_types=1);

namespace UniversalMarkdownEditor;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('UME_VERSION')) {
    define('UME_VERSION', '0.1.0');
}

if (!defined('UME_FILE')) {
    define('UME_FILE', __FILE__);
}

if (!defined('UME_DIR')) {
    define('UME_DIR', plugin_dir_path(__FILE__));
}

if (!defined('UME_MINIMUM_PHP')) {
    define('UME_MINIMUM_PHP', '8.1.0');
}

if (!defined('UME_MINIMUM_WORDPRESS')) {
    define('UME_MINIMUM_WORDPRESS', '6.4');
}

$showCompatibilityNotice = static function (string $message): void {
    add_action(
        'admin_notices',
        static function () use ($message): void {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    );
};

$wordpressVersion = isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : '0';
$supported = version_compare(PHP_VERSION, UME_MINIMUM_PHP, '>=')
    && version_compare($wordpressVersion, UME_MINIMUM_WORDPRESS, '>=');

if (!$supported) {
    $showCompatibilityNotice(
        sprintf(
            'Universal Markdown Editor requires PHP %s or later and WordPress %s or later.',
            UME_MINIMUM_PHP,
            UME_MINIMUM_WORDPRESS
        )
    );

    return;
}

require_once UME_DIR . 'src/autoload.php';

$autoloader = UME_DIR . 'build/vendor/autoload.php';

if (!is_file($autoloader)) {
    $autoloader = UME_DIR . 'vendor/autoload.php';
}

if (!is_file($autoloader)) {
    $showCompatibilityNotice(
        'Universal Markdown Editor dependencies are missing. Run composer install from the plugin directory.'
    );

    return;
}

require_once $autoloader;

Plugin::boot();
