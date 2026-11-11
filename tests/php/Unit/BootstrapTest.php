<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function testUnsupportedWordPressShowsNoticeWithoutLeakingEntrypointVariables(): void
    {
        [$status, $output, $errors] = $this->runPhp(
            $this->wordpressStubs('6.3')
            . $this->noticeCapture($this->entrypointRequire(
                dirname(__DIR__, 3) . '/universal-markdown-editor.php'
            ))
        );

        self::assertSame(0, $status, $errors);
        self::assertSame('', $errors);
        $result = $this->decodeResult($output);
        self::assertSame(
            ['showCompatibilityNotice' => false, 'wordpressVersion' => false, 'supported' => false, 'autoloader' => false],
            $result['globals']
        );
        self::assertSame(1, $result['noticeCount']);
        self::assertStringContainsString('requires PHP 8.1.0 or later and WordPress 6.4 or later', $result['notice']);
        self::assertFalse($result['pluginClass']);
    }

    public function testSupportedWordPressWithMissingDependenciesShowsNoticeWithoutGlobalVariables(): void
    {
        $root = sys_get_temp_dir() . '/ume-bootstrap-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root . '/src', 0777, true));
        self::assertTrue(copy(
            dirname(__DIR__, 3) . '/universal-markdown-editor.php',
            $root . '/universal-markdown-editor.php'
        ));
        file_put_contents($root . '/src/autoload.php', "<?php\n");

        try {
            [$status, $output, $errors] = $this->runPhp(
                $this->wordpressStubs('6.4')
                . $this->noticeCapture($this->entrypointRequire($root . '/universal-markdown-editor.php'))
            );

            self::assertSame(0, $status, $errors);
            self::assertSame('', $errors);
            $result = $this->decodeResult($output);
            self::assertSame(
                ['showCompatibilityNotice' => false, 'wordpressVersion' => false, 'supported' => false, 'autoloader' => false],
                $result['globals']
            );
            self::assertSame(1, $result['noticeCount']);
            self::assertStringContainsString('dependencies are missing', $result['notice']);
            self::assertFalse($result['pluginClass']);
        } finally {
            foreach (['/src/autoload.php', '/universal-markdown-editor.php'] as $file) {
                @unlink($root . $file);
            }
            @rmdir($root . '/src');
            @rmdir($root);
        }
    }

    private function wordpressStubs(string $wordpressVersion): string
    {
        return 'define("ABSPATH", "/tmp/");'
            . '$GLOBALS["wp_version"] = ' . var_export($wordpressVersion, true) . ';'
            . '$GLOBALS["ume_test_actions"] = [];'
            . 'function plugin_dir_path($file) { return rtrim(dirname($file), "/") . "/"; }'
            . 'function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1) {'
            . '$GLOBALS["ume_test_actions"][] = [$hook, $callback];'
            . '}'
            . 'function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, "UTF-8"); }'
            . 'function __($text) { return $text; }';
    }

    private function entrypointRequire(string $entrypoint): string
    {
        return 'ob_start();'
            . 'require ' . var_export($entrypoint, true) . ';'
            . 'ob_end_clean();';
    }

    private function noticeCapture(string $entrypoint): string
    {
        return $entrypoint
            . '$notice = ""; $noticeCount = 0;'
            . 'foreach ($GLOBALS["ume_test_actions"] as [$hook, $callback]) {'
            . 'if ($hook !== "admin_notices") { continue; }'
            . '++$noticeCount; ob_start(); $callback(); $notice .= ob_get_clean();'
            . '}'
            . 'echo json_encode(['
            . '"globals" => ['
            . '"showCompatibilityNotice" => array_key_exists("showCompatibilityNotice", $GLOBALS),'
            . '"wordpressVersion" => array_key_exists("wordpressVersion", $GLOBALS),'
            . '"supported" => array_key_exists("supported", $GLOBALS),'
            . '"autoloader" => array_key_exists("autoloader", $GLOBALS)'
            . '],'
            . '"noticeCount" => $noticeCount,'
            . '"notice" => $notice,'
            . '"pluginClass" => class_exists("UniversalMarkdownEditor\\\\Plugin")'
            . ']);';
    }

    /** @return array{globals: array<string, bool>, noticeCount: int, notice: string, pluginClass: bool} */
    private function decodeResult(string $output): array
    {
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);

        return $result;
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
