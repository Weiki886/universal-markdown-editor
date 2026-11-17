<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Post;

use UniversalMarkdownEditor\Markdown\ConversionException;
use UniversalMarkdownEditor\Markdown\Converter;

final class PreservedBlockRenderer
{
    private Converter $converter;

    public function __construct(?Converter $converter = null)
    {
        $this->converter = $converter ?? new Converter();
    }

    /** @param array<string, string> $blocks
     *  @throws ConversionException
     */
    public function render(string $source, array $blocks): string
    {
        preg_match_all(GutenbergImporter::MARKER_PATTERN, $source, $matches, PREG_OFFSET_CAPTURE);
        $references = array_map(static fn (array $match): string => $match[0], $matches[1] ?? []);
        $stripped = preg_replace(GutenbergImporter::MARKER_PATTERN, '', $source);

        if ($stripped === null || preg_match('/ume-preserved-block/i', $stripped) === 1) {
            throw ConversionException::invalidSource('Preserved block markers are malformed.');
        }

        foreach ($matches[0] as [$marker, $offset]) {
            $lineStart = strrpos(substr($source, 0, $offset), "\n");
            $lineEnd = strpos($source, "\n", $offset);
            $line = trim(substr($source, $lineStart === false ? 0 : $lineStart + 1, ($lineEnd === false ? strlen($source) : $lineEnd) - ($lineStart === false ? 0 : $lineStart + 1)));
            if ($line !== $marker) {
                throw ConversionException::invalidSource('Preserved block markers must occupy their own line.');
            }
        }

        $expected = array_map('strval', array_keys($blocks));
        if ($references !== $expected) {
            throw ConversionException::invalidSource('Preserved block references are missing, duplicated, or out of order.');
        }

        if ($references === []) {
            return $this->converter->convert($source);
        }

        $parts = preg_split(GutenbergImporter::MARKER_PATTERN, $source);
        if ($parts === false || count($parts) !== count($references) + 1) {
            throw ConversionException::invalidSource('Preserved block markers are invalid.');
        }

        $html = '';
        foreach ($references as $index => $reference) {
            $html .= $this->converter->convert($parts[$index]);
            $html .= $blocks[$reference];
        }
        $html .= $this->converter->convert($parts[count($parts) - 1]);

        return $html;
    }
}
