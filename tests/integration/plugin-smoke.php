<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "The plugin smoke test must run inside WordPress.\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$wpdb = $GLOBALS['wpdb'];
$rowCount = static function (string $table) use ($wpdb): int {
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
};
$pluginBasename = plugin_basename(UME_FILE);
$pluginHeaders = get_file_data(
    UME_FILE,
    [
        'name' => 'Plugin Name',
        'version' => 'Version',
        'requiresAtLeast' => 'Requires at least',
        'requiresPHP' => 'Requires PHP',
        'license' => 'License',
    ],
    'plugin'
);
$wordpressVersion = get_bloginfo('version');

$check(is_plugin_active($pluginBasename), 'The plugin is not active.');
$check(defined('UME_VERSION') && UME_VERSION === '0.1.0', 'UME_VERSION is missing or incorrect.');
$check($pluginHeaders['name'] === 'Universal Markdown Editor for WordPress', 'The plugin name header is incorrect.');
$check($pluginHeaders['version'] === '0.1.0', 'The plugin version header is incorrect.');
$check($pluginHeaders['requiresAtLeast'] === '6.4', 'The minimum WordPress version header is incorrect.');
$check($pluginHeaders['requiresPHP'] === '8.1', 'The minimum PHP version header is incorrect.');
$check($pluginHeaders['license'] === 'GPL-2.0-or-later', 'The plugin license header is incorrect.');
$check(version_compare(PHP_VERSION, '8.1', '>='), 'The integration environment uses an unsupported PHP version.');
$check($wordpressVersion === '7.1.4', 'The integration environment is not pinned to WordPress 7.1.4.');
$check(class_exists(\UniversalMarkdownEditor\Plugin::class), 'The namespaced Plugin class did not load.');
$check(class_exists(\UniversalMarkdownEditor\Lifecycle::class), 'The namespaced Lifecycle class did not load.');
$check(class_exists(\UniversalMarkdownEditor\Admin\Assets::class), 'The namespaced Assets class did not load.');
$check(class_exists(\UniversalMarkdownEditor\REST\MarkdownPreviewController::class), 'The Markdown preview controller did not load.');
$check(class_exists(\UniversalMarkdownEditor\REST\MarkdownDocumentController::class), 'The Markdown document controller did not load.');
$check(class_exists(\UniversalMarkdownEditor\Post\MarkdownRepository::class), 'The Markdown repository did not load.');
$check(class_exists(\UniversalMarkdownEditor\Post\SaveService::class), 'The Markdown save service did not load.');
$check(
    has_action('admin_enqueue_scripts', [\UniversalMarkdownEditor\Admin\Assets::class, 'enqueue']) !== false,
    'The admin asset hook is not registered.'
);
$check(
    has_action('rest_api_init', [\UniversalMarkdownEditor\REST\MarkdownPreviewController::class, 'registerRoutes']) !== false,
    'The Markdown preview REST route hook is not registered.'
);
$check(
    has_action('rest_api_init', [\UniversalMarkdownEditor\REST\MarkdownDocumentController::class, 'registerRoutes']) !== false,
    'The Markdown document REST route hook is not registered.'
);
$check(
    has_action('init', [\UniversalMarkdownEditor\Post\MarkdownRepository::class, 'registerMeta']) !== false,
    'The private Markdown metadata registration hook is not registered.'
);
$check(
    has_action('deactivate_' . $pluginBasename, [\UniversalMarkdownEditor\Lifecycle::class, 'deactivate']) !== false,
    'The deactivation lifecycle hook is not registered.'
);

$lifecycleEvents = [];
add_action('ume_deactivated', static function () use (&$lifecycleEvents): void {
    $lifecycleEvents[] = 'deactivated';
});
add_action('ume_activated', static function () use (&$lifecycleEvents): void {
    $lifecycleEvents[] = 'activated';
});

$postsBefore = $rowCount($wpdb->posts);
$postmetaBefore = $rowCount($wpdb->postmeta);

deactivate_plugins($pluginBasename);

$check(!is_plugin_active($pluginBasename), 'The plugin could not be deactivated.');
$check($rowCount($wpdb->posts) === $postsBefore, 'Deactivation changed the posts table.');
$check($rowCount($wpdb->postmeta) === $postmetaBefore, 'Deactivation changed the postmeta table.');

$activationResult = activate_plugin($pluginBasename);

$check($activationResult === null, 'The plugin could not be reactivated without an error.');
$check(is_plugin_active($pluginBasename), 'The plugin is not active after reactivation.');
$check($rowCount($wpdb->posts) === $postsBefore, 'Activation changed the posts table.');
$check($rowCount($wpdb->postmeta) === $postmetaBefore, 'Activation changed the postmeta table.');
$check(
    $lifecycleEvents === ['deactivated', 'activated'],
    'The activation or deactivation action did not fire in the expected order.'
);

$GLOBALS['current_screen'] = WP_Screen::get('post');
$adminIds = get_users([
    'role' => 'administrator',
    'number' => 1,
    'fields' => 'ID',
]);
$adminId = $adminIds === [] ? 0 : (int) $adminIds[0];
wp_set_current_user($adminId);
\UniversalMarkdownEditor\Admin\Assets::enqueue('post.php');

$check(wp_script_is('ume-admin', 'enqueued'), 'The editor script was not enqueued on the post editor.');
$check(wp_style_is('ume-admin', 'enqueued'), 'The editor style was not enqueued on the post editor.');
$check(wp_script_is('ume-admin', 'registered'), 'The editor script was not registered.');
$check(wp_script_is('media-editor', 'enqueued'), 'The authorized post editor did not enqueue the WordPress media library.');
$localizedSettings = wp_scripts()->get_data('ume-admin', 'data');
$check(
    is_string($localizedSettings)
        && str_contains($localizedSettings, '"canUploadMedia":true'),
    'The editor did not receive the current media upload capability.'
);

wp_dequeue_script('ume-admin');
wp_dequeue_style('ume-admin');
wp_dequeue_script('media-editor');
wp_set_current_user(0);
\UniversalMarkdownEditor\Admin\Assets::enqueue('post.php');
$unauthorizedSettings = wp_scripts()->get_data('ume-admin', 'data');
$check(
    !wp_script_is('media-editor', 'enqueued'),
    'A user without upload_files caused the WordPress media library to be enqueued.'
);
$check(
    is_string($unauthorizedSettings)
        && str_contains($unauthorizedSettings, '"canUploadMedia":false'),
    'A user without upload_files received media insertion capability.'
);
wp_dequeue_script('ume-admin');
wp_dequeue_style('ume-admin');
wp_set_current_user($adminId);

$nonEditorScreens = [
    'post list' => ['edit.php', WP_Screen::get('edit-post')],
    'page editor' => ['post.php', WP_Screen::get('page')],
    'site editor' => ['site-editor.php', WP_Screen::get('site-editor')],
    'missing screen data' => ['post.php', null],
];

foreach ($nonEditorScreens as $label => [$hookSuffix, $screen]) {
    $GLOBALS['current_screen'] = $screen;
    \UniversalMarkdownEditor\Admin\Assets::enqueue($hookSuffix);

    $check(!wp_script_is('ume-admin', 'enqueued'), "The editor script was enqueued on {$label}.");
    $check(!wp_style_is('ume-admin', 'enqueued'), "The editor style was enqueued on {$label}.");
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    exit(1);
}

printf(
    "Plugin smoke test passed on WordPress %s, PHP %s, %s.\n",
    $wordpressVersion,
    PHP_VERSION,
    $pluginBasename
);
