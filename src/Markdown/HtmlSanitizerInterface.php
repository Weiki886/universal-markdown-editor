<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Markdown;

interface HtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
