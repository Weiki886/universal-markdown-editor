<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit\Markdown;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Output\RenderedContentInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UniversalMarkdownEditor\Markdown\ConversionException;
use UniversalMarkdownEditor\Markdown\Converter;
use UniversalMarkdownEditor\Markdown\HtmlSanitizer;
use UniversalMarkdownEditor\Markdown\HtmlSanitizerInterface;

final class ConverterTest extends TestCase
{
    public function testMarkdownCoreApiIsAvailable(): void
    {
        self::assertTrue(class_exists(Converter::class));
        self::assertTrue(class_exists(ConversionException::class));
        self::assertTrue(class_exists(HtmlSanitizer::class));
        self::assertTrue(interface_exists(HtmlSanitizerInterface::class));
    }

    public function testGoldenFixtureIsStableAndRepeatable(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());
        $source = self::fixture('basic.md');
        $expected = self::fixture('basic.html');

        $first = $converter->convert($source);

        self::assertSame($expected, $first);
        self::assertSame($first, $converter->convert($source));
    }

    public function testConverterVersionMatchesTheAcceptedContract(): void
    {
        self::assertSame(1, Converter::VERSION);
    }

    public function testEmptySourceProducesEmptyHtml(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        self::assertSame('', $converter->convert(''));
    }

    public function testUnicodeSourceIsPreserved(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        self::assertSame("<p>中文 😀</p>\n", $converter->convert('中文 😀'));
    }

    public function testTaskListExtensionIsNotEnabled(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        $html = $converter->convert('- [x] Done');

        self::assertSame("<ul>\n<li>[x] Done</li>\n</ul>\n", $html);
        self::assertStringNotContainsString('checkbox', $html);
    }

    public function testOrderedListStartIsPreserved(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        self::assertSame("<ol start=\"3\">\n<li>Third</li>\n</ol>\n", $converter->convert('3. Third'));
    }

    public function testRawHtmlIsEscapedRatherThanRendered(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        self::assertSame(
            "<p>&lt;span onclick=\"alert(1)\"&gt;Safe&lt;/span&gt;</p>\n",
            $converter->convert('<span onclick="alert(1)">Safe</span>')
        );
    }

    /**
     * @dataProvider unsafeMarkdownLinks
     */
    public function testUnsafeMarkdownLinksLoseTheirDestination(string $source, string $expected): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        self::assertSame($expected, $converter->convert($source));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeMarkdownLinks(): iterable
    {
        yield 'javascript link' => ['[link](javascript:alert)', "<p><a>link</a></p>\n"];
        yield 'vbscript link' => ['[link](vbscript:msgbox)', "<p><a>link</a></p>\n"];
        yield 'file link' => ['[link](file:///etc/passwd)', "<p><a>link</a></p>\n"];
        yield 'data link' => ['[link](data:text/html,hello)', "<p><a>link</a></p>\n"];
    }

    public function testExactlyOneMibOfSourceIsAccepted(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());
        $source = str_repeat('a', Converter::MAX_SOURCE_BYTES);

        $html = $converter->convert($source);

        self::assertSame(strlen($source) + 8, strlen($html));
        self::assertStringStartsWith('<p>aaaa', $html);
        self::assertStringEndsWith("aaaa</p>\n", $html);
    }

    public function testSourceAboveOneMibIsRejectedBeforeParsing(): void
    {
        $parser = $this->createMock(ConverterInterface::class);
        $parser->expects(self::never())->method('convert');
        $converter = new Converter($parser, $this->passThroughSanitizer());

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('1 MiB');

        try {
            $converter->convert(str_repeat('a', Converter::MAX_SOURCE_BYTES + 1));
        } catch (ConversionException $exception) {
            self::assertSame('ume_invalid_source', $exception->errorCode());
            throw $exception;
        }
    }

    public function testInvalidUtf8IsRejectedAsInvalidSource(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        $this->expectException(ConversionException::class);

        try {
            $converter->convert("invalid\xB1\x31");
        } catch (ConversionException $exception) {
            self::assertSame('ume_invalid_source', $exception->errorCode());
            throw $exception;
        }
    }

    public function testExcessiveNestingIsBoundedWithoutFatalError(): void
    {
        $converter = new Converter(null, $this->passThroughSanitizer());

        $html = $converter->convert(str_repeat('> ', 120) . 'deep');

        self::assertNotSame('', $html);
        self::assertLessThanOrEqual(100, substr_count($html, '<blockquote>'));
        self::assertStringContainsString('deep', $html);
    }

    public function testParserFailureIsWrappedWithoutInvokingTheSanitizer(): void
    {
        $failure = new RuntimeException('Parser failed.');
        $parser = $this->createMock(ConverterInterface::class);
        $parser->method('convert')->willThrowException($failure);
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->expects(self::never())->method('sanitize');
        $converter = new Converter($parser, $sanitizer);

        try {
            $converter->convert('private source');
            self::fail('A parser failure must not return HTML.');
        } catch (ConversionException $exception) {
            self::assertSame('ume_conversion_failed', $exception->errorCode());
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringNotContainsString('private source', $exception->getMessage());
        }
    }

    public function testSanitizerFailureDoesNotReturnPartialHtml(): void
    {
        $failure = new RuntimeException('Sanitizer failed.');
        $rendered = $this->createMock(RenderedContentInterface::class);
        $rendered->method('getContent')->willReturn('<p>partial secret</p>');
        $parser = $this->createMock(ConverterInterface::class);
        $parser->method('convert')->willReturn($rendered);
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willThrowException($failure);
        $converter = new Converter($parser, $sanitizer);

        try {
            $converter->convert('source');
            self::fail('A sanitizer failure must not return partial HTML.');
        } catch (ConversionException $exception) {
            self::assertSame('ume_conversion_failed', $exception->errorCode());
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringNotContainsString('partial secret', $exception->getMessage());
        }
    }

    private function passThroughSanitizer(): HtmlSanitizerInterface
    {
        return new class implements HtmlSanitizerInterface {
            public function sanitize(string $html): string
            {
                return $html;
            }
        };
    }

    private static function fixture(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/fixtures/markdown/' . $name;
        $contents = file_get_contents($path);

        self::assertIsString($contents, "Fixture could not be read: {$path}");

        return $contents;
    }
}
