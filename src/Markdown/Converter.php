<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Markdown;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use Throwable;

final class Converter
{
    public const VERSION = 1;
    public const MAX_SOURCE_BYTES = 1048576;

    private ConverterInterface $parser;
    private HtmlSanitizerInterface $sanitizer;

    public function __construct(?ConverterInterface $parser = null, ?HtmlSanitizerInterface $sanitizer = null)
    {
        $this->parser = $parser ?? self::createDefaultParser();
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer();
    }

    /**
     * Convert Markdown source to sanitized HTML.
     *
     * @throws ConversionException When the source is invalid or conversion fails.
     */
    public function convert(string $source): string
    {
        if (strlen($source) > self::MAX_SOURCE_BYTES) {
            throw ConversionException::invalidSource('Markdown source exceeds the 1 MiB limit.');
        }

        if (preg_match('//u', $source) !== 1) {
            throw ConversionException::invalidSource('Markdown source must be valid UTF-8.');
        }

        try {
            return $this->sanitizer->sanitize($this->parser->convert($source)->getContent());
        } catch (Throwable $exception) {
            throw ConversionException::conversionFailed($exception);
        }
    }

    private static function createDefaultParser(): ConverterInterface
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'max_delimiters_per_line' => 1000,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());

        return new MarkdownConverter($environment);
    }
}
