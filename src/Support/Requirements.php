<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Support;

final class Requirements
{
    public const MINIMUM_PHP = '8.1.0';
    public const MINIMUM_WORDPRESS = '6.4';

    public static function isSupported(string $phpVersion, string $wordpressVersion): bool
    {
        return version_compare($phpVersion, self::MINIMUM_PHP, '>=')
            && version_compare($wordpressVersion, self::MINIMUM_WORDPRESS, '>=');
    }
}
