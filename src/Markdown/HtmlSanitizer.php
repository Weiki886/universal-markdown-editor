<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Markdown;

use WP_HTML_Tag_Processor;

final class HtmlSanitizer implements HtmlSanitizerInterface
{
    private const DENIED_PROTOCOLS = ['javascript', 'vbscript', 'file', 'data'];

    /**
     * Tags and attributes required by the enabled Markdown extensions.
     *
     * @var array<string, array<string, bool>>
     */
    private const ALLOWED_HTML = [
        'p' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'strong' => [],
        'em' => [],
        'del' => [],
        'ul' => [],
        'ol' => ['start' => true],
        'li' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => ['class' => true],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['align' => true],
        'td' => ['align' => true],
        'a' => ['href' => true, 'title' => true],
        'img' => [
            'src' => true,
            'alt' => true,
            'title' => true,
            'width' => true,
            'height' => true,
        ],
        'hr' => [],
        'br' => [],
    ];

    public function sanitize(string $html): string
    {
        $html = wp_kses($html, self::ALLOWED_HTML, $this->allowedProtocols());
        $processor = new WP_HTML_Tag_Processor($html);

        while ($processor->next_tag('code')) {
            $class = $processor->get_attribute('class');

            if (!is_string($class) || preg_match('/^language-[A-Za-z0-9_+#.-]+$/D', $class) !== 1) {
                $processor->remove_attribute('class');
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * @return array<int, string>
     */
    private function allowedProtocols(): array
    {
        return array_values(array_filter(
            wp_allowed_protocols(),
            static function ($protocol): bool {
                return is_string($protocol)
                    && !in_array(strtolower($protocol), self::DENIED_PROTOCOLS, true);
            }
        ));
    }
}
