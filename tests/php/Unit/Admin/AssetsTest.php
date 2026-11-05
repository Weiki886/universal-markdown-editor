<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use UniversalMarkdownEditor\Admin\Assets;

final class AssetsTest extends TestCase
{
    public function testAssetsGateIsAvailable(): void
    {
        self::assertTrue(class_exists(Assets::class));
    }

    public function testAssetsAreEnqueuedOnPostEditorScreens(): void
    {
        $screen = (object) [
            'base' => 'post',
            'post_type' => 'post',
        ];

        self::assertTrue(Assets::shouldEnqueue('post.php', $screen));
        self::assertTrue(Assets::shouldEnqueue('post-new.php', $screen));
    }

    /**
     * @dataProvider nonPostEditorScreens
     */
    public function testAssetsAreNotEnqueuedOutsidePostEditorScreens(string $hookSuffix, object $screen): void
    {
        self::assertFalse(Assets::shouldEnqueue($hookSuffix, $screen));
    }

    /**
     * @return iterable<string, array{string, object}>
     */
    public static function nonPostEditorScreens(): iterable
    {
        yield 'post list' => ['edit.php', (object) ['base' => 'edit', 'post_type' => 'post']];
        yield 'page editor' => ['post.php', (object) ['base' => 'post', 'post_type' => 'page']];
        yield 'site editor' => ['site-editor.php', (object) ['base' => 'site-editor', 'post_type' => '']];
        yield 'missing screen' => ['post.php', (object) []];
    }
}
