<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "The localization test must run inside WordPress.\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$failures = [];
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    ++$assertions;

    if (!$condition) {
        $failures[] = $message;
    }
};

$domain = 'universal-markdown-editor';
$sourceMessage = 'The requested post does not exist or is not supported.';
$translatedMessage = '请求的文章不存在或不受支持。';
$headers = get_file_data(
    UME_FILE,
    [
        'textDomain' => 'Text Domain',
        'domainPath' => 'Domain Path',
    ],
    'plugin'
);
$languagesDirectory = UME_DIR . 'languages';

$check($headers['textDomain'] === $domain, 'The plugin text domain header is incorrect.');
$check(trim($headers['domainPath'], '/') === 'languages', 'The plugin Domain Path header is not languages.');
$check(is_dir($languagesDirectory), 'The bundled languages directory is missing.');

foreach ([
    'universal-markdown-editor.pot',
    'universal-markdown-editor-zh_CN.po',
    'universal-markdown-editor-zh_CN.mo',
    'universal-markdown-editor-zh_CN-ume-admin.json',
] as $translationFile) {
    $check(
        is_file($languagesDirectory . '/' . $translationFile),
        "The bundled translation file is missing: {$translationFile}"
    );
}

$check(
    __($sourceMessage, $domain) === $sourceMessage,
    'The English source message did not remain unchanged before switching locales.'
);
$useSimplifiedChinese = static fn (): string => 'zh_CN';
add_filter('pre_determine_locale', $useSimplifiedChinese);
unload_textdomain($domain, true);

$check(
    load_textdomain(
        $domain,
        $languagesDirectory . '/universal-markdown-editor-zh_CN.mo'
    ),
    'The bundled PHP translation file could not be loaded.'
);

$check(
    __($sourceMessage, $domain) === $translatedMessage,
    'The bundled PHP translation was not loaded for zh_CN.'
);
$postResult = (new \UniversalMarkdownEditor\Post\SaveService())->read(PHP_INT_MAX);
$check(
    is_wp_error($postResult) && $postResult->get_error_message() === $translatedMessage,
    'A user-visible REST error was not translated for zh_CN.'
);

$adminIds = get_users([
    'role' => 'administrator',
    'number' => 1,
    'fields' => 'ID',
]);
wp_set_current_user($adminIds === [] ? 0 : (int) $adminIds[0]);
$GLOBALS['current_screen'] = WP_Screen::get('post');
\UniversalMarkdownEditor\Admin\Assets::enqueue('post.php');

$registeredScript = wp_scripts()->registered['ume-admin'] ?? null;
$check(
    is_object($registeredScript)
        && ($registeredScript->textdomain ?? null) === $domain
        && ($registeredScript->translations_path ?? null) === $languagesDirectory,
    'The editor script did not register its translation file path.'
);

$scriptTranslations = load_script_textdomain('ume-admin', $domain, $languagesDirectory);
$decodedTranslations = is_string($scriptTranslations)
    ? json_decode($scriptTranslations, true)
    : null;
$check(
    is_array($decodedTranslations)
        && ($decodedTranslations['locale_data'][$domain]['Markdown editor'][0] ?? null) === 'Markdown 编辑器',
    'The bundled JavaScript translation was not loaded for zh_CN.'
);

remove_filter('pre_determine_locale', $useSimplifiedChinese);
unload_textdomain($domain, true);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    exit(1);
}

printf("Localization integration test passed (%d assertions).\n", $assertions);
