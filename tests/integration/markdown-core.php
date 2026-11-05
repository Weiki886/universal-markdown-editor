<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "The Markdown core test must run inside WordPress.\n");
    exit(1);
}

use UniversalMarkdownEditor\Markdown\Converter;
use UniversalMarkdownEditor\Markdown\HtmlSanitizer;

$failures = [];
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    ++$assertions;

    if (!$condition) {
        $failures[] = $message;
    }
};
$checkSame = static function (string $expected, string $actual, string $message) use ($check): void {
    $check($expected === $actual, $message . "\nExpected:\n" . $expected . "\nActual:\n" . $actual);
};
$fixture = static function (string $name): string {
    $contents = file_get_contents(__DIR__ . '/../fixtures/markdown/' . $name);

    if (!is_string($contents)) {
        throw new RuntimeException("Could not read Markdown fixture: {$name}");
    }

    return $contents;
};

$converter = new Converter();
$sanitizer = new HtmlSanitizer();
$source = $fixture('basic.md');
$expected = $fixture('basic.html');
$converted = $converter->convert($source);

$checkSame($expected, $converted, 'The complete conversion pipeline must match the golden fixture.');
$checkSame($converted, $converter->convert($source), 'The complete conversion pipeline must be deterministic.');

$safe = $sanitizer->sanitize(
    '<h2>Heading</h2><ol start="3"><li>Item</li></ol>' .
    '<a href="https://example.com" title="Title" target="_blank" rel="noopener">Link</a>' .
    '<img src="/image.png" alt="Alt" title="Title" width="640" height="480" />' .
    '<pre><code class="language-php">echo 1;</code></pre>' .
    '<table><thead><tr><th align="left">Head</th></tr></thead></table>'
);
$check(str_contains($safe, '<h2>Heading</h2>'), 'An allowed heading was removed.');
$check(str_contains($safe, '<ol start="3">'), 'The ordered-list start attribute was not preserved.');
$check(str_contains($safe, 'href="https://example.com"'), 'A safe HTTPS link was removed.');
$check(str_contains($safe, 'title="Title"'), 'A necessary title attribute was removed.');
$check(!str_contains($safe, 'target='), 'A link target attribute bypassed the whitelist.');
$check(!str_contains($safe, 'rel='), 'A link rel attribute bypassed the whitelist.');
$check(str_contains($safe, 'src="/image.png"'), 'A safe relative image source was removed.');
$check(str_contains($safe, 'alt="Alt"'), 'The image alt attribute was removed.');
$check(str_contains($safe, 'width="640"'), 'The image width attribute was removed.');
$check(str_contains($safe, 'height="480"'), 'The image height attribute was removed.');
$check(str_contains($safe, '<code class="language-php">'), 'A parser-generated language class was removed.');
$check(str_contains($safe, '<th align="left">'), 'A table alignment attribute was removed.');

$unsafe = $sanitizer->sanitize(
    '<div><p onclick="alert(1)" style="color:red">Safe</p>' .
    '<script>alert(1)</script><iframe src="https://example.com"></iframe>' .
    '<form><input value="x"></form></div>'
);
$check(str_contains($unsafe, '<p>Safe</p>'), 'Safe paragraph content was not preserved.');
foreach (['<div', '<script', '<iframe', '<form', '<input', 'onclick=', 'style='] as $fragment) {
    $check(!str_contains($unsafe, $fragment), "Unsafe markup survived sanitization: {$fragment}");
}

$dangerousUrls = [
    'javascript' => ['javascript:alert(1)', 'javascript'],
    'mixed-case javascript' => ['JaVaScRiPt:alert(1)', 'javascript'],
    'entity-obfuscated javascript' => ['java&#x73;cript:alert(1)', 'javascript'],
    'vbscript' => ['vbscript:msgbox', 'vbscript'],
    'file' => ['file:///etc/passwd', 'file'],
    'data HTML' => ['data:text/html;base64,PHNjcmlwdD4=', 'data'],
    'data image' => ['data:image/png;base64,AAAA', 'data'],
];
foreach ($dangerousUrls as $label => [$url, $scheme]) {
    $filtered = $sanitizer->sanitize('<a href="' . $url . '">link</a><img src="' . $url . '" alt="image" />');
    $processor = new WP_HTML_Tag_Processor($filtered);
    $href = null;
    $src = null;

    while ($processor->next_tag()) {
        if ($processor->get_tag() === 'A') {
            $href = $processor->get_attribute('href');
        }

        if ($processor->get_tag() === 'IMG') {
            $src = $processor->get_attribute('src');
        }
    }

    $decodedHref = is_string($href) ? strtolower(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    $decodedSrc = is_string($src) ? strtolower(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    $check(!str_contains($decodedHref, $scheme . ':'), "The {$label} link retained a dangerous protocol.");
    $check(!str_contains($decodedSrc, $scheme . ':'), "The {$label} image retained a dangerous protocol.");
}

$safeUrls = [
    'https://example.com',
    'http://example.com',
    'mailto:editor@example.com',
    '/relative-post',
    '#section',
];
foreach ($safeUrls as $url) {
    $filtered = $sanitizer->sanitize('<a href="' . $url . '">link</a>');
    $check(str_contains($filtered, 'href="' . $url . '"'), "A safe URL was removed: {$url}");
}

$addDataProtocol = static function (array $protocols): array {
    $protocols[] = 'data';
    return $protocols;
};
add_filter('wp_allowed_protocols', $addDataProtocol);
$filteredData = $sanitizer->sanitize('<a href="data:text/html,hello">data</a>');
remove_filter('wp_allowed_protocols', $addDataProtocol);
$check(!str_contains(strtolower(html_entity_decode($filteredData, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'data:'), 'A WordPress protocol filter could re-enable data URLs.');

$dataImage = $converter->convert('![image](data:image/png;base64,AAAA)');
$check(!str_contains($dataImage, 'data:'), 'A CommonMark-safe data image bypassed the plugin policy.');
$check(str_contains($dataImage, '<img'), 'The sanitized image element was not preserved.');
$check(str_contains($dataImage, 'alt="image"'), 'The safe data image alt text was not preserved.');

$rawHtml = $converter->convert('<script>alert(1)</script>');
$check(!str_contains($rawHtml, '<script'), 'Raw script HTML was rendered instead of escaped.');
$check(str_contains($rawHtml, '&lt;script&gt;'), 'Raw HTML was not visibly escaped.');

$codeClasses = $sanitizer->sanitize(
    '<pre><code class="language-c++">one</code></pre>' .
    '<pre><code class="not-language" id="injected">two</code></pre>'
);
$check(str_contains($codeClasses, 'class="language-c++"'), 'A valid parser-generated language class was removed.');
$check(!str_contains($codeClasses, 'not-language'), 'An arbitrary code class survived sanitization.');
$check(!str_contains($codeClasses, 'id='), 'An arbitrary code id survived sanitization.');

$codeInfoInjection = $converter->convert("```php\" onclick=\"alert(1)\necho 1;\n```");
$check(!str_contains($codeInfoInjection, 'onclick='), 'A code info string injected an event attribute.');
$check(!str_contains($codeInfoInjection, 'class='), 'A malformed code language class was not removed.');

$wpdb = $GLOBALS['wpdb'];
$postsBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$postmetaBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$converter->convert("# Read only\n\nNo persistence.");
$check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}") === $postsBefore, 'Conversion changed the posts table.');
$check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}") === $postmetaBefore, 'Conversion changed the postmeta table.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, "Markdown core integration test failed with " . count($failures) . " failure(s) across {$assertions} assertions.\n");
    exit(1);
}

printf(
    "Markdown core integration test passed on WordPress %s, PHP %s; %d assertions.\n",
    get_bloginfo('version'),
    PHP_VERSION,
    $assertions
);
