<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function testDirectAccessStopsBeforeExecutingCallerCode(): void
    {
        [$status, $output, $errors] = $this->runPhp(
            'require ' . var_export(dirname(__DIR__, 3) . '/src/autoload.php', true) . ';'
            . 'echo "caller continued";'
        );

        self::assertSame(0, $status);
        self::assertSame('', $output);
        self::assertSame('', $errors);
    }

    public function testWordPressBootstrapAllowsPluginClassesToLoad(): void
    {
        [$status, $output, $errors] = $this->runPhp(
            'define("ABSPATH", ' . var_export(dirname(__DIR__, 3) . '/', true) . ');'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/src/autoload.php', true) . ';'
            . 'echo json_encode(['
            . 'class_exists("UniversalMarkdownEditor\\Markdown\\ConversionException"),'
            . 'class_exists("UnrelatedNamespace\\MissingClass"),'
            . 'class_exists("UniversalMarkdownEditor\\MissingClass")]);'
        );

        self::assertSame(0, $status);
        self::assertSame('[true,false,false]', $output);
        self::assertSame('', $errors);
    }

    /** @return array{int, string, string} */
    private function runPhp(string $script): array
    {
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        self::assertIsString($output);
        self::assertIsString($errors);

        return [$status, $output, $errors];
    }
}
