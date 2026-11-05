<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use UniversalMarkdownEditor\Support\Requirements;

final class RequirementsTest extends TestCase
{
    public function testRequirementsGateIsAvailable(): void
    {
        self::assertTrue(class_exists(Requirements::class));
    }

    /**
     * @dataProvider supportedVersions
     */
    public function testSupportedVersionsPassTheGate(string $phpVersion, string $wordpressVersion): void
    {
        self::assertTrue(Requirements::isSupported($phpVersion, $wordpressVersion));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supportedVersions(): iterable
    {
        yield 'minimum versions' => ['8.1.0', '6.4'];
        yield 'newer versions' => ['8.4.14', '6.8.3'];
    }

    /**
     * @dataProvider unsupportedVersions
     */
    public function testUnsupportedVersionsFailTheGate(string $phpVersion, string $wordpressVersion): void
    {
        self::assertFalse(Requirements::isSupported($phpVersion, $wordpressVersion));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedVersions(): iterable
    {
        yield 'php below minimum' => ['8.0.99', '6.4'];
        yield 'wordpress below minimum' => ['8.1.0', '6.3.9'];
    }
}
