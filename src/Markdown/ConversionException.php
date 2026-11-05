<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Markdown;

use RuntimeException;
use Throwable;

final class ConversionException extends RuntimeException
{
    public const INVALID_SOURCE = 'ume_invalid_source';
    public const CONVERSION_FAILED = 'ume_conversion_failed';

    private string $errorCode;

    public function __construct(string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public static function invalidSource(string $message): self
    {
        return new self(self::INVALID_SOURCE, $message);
    }

    public static function conversionFailed(Throwable $previous): self
    {
        return new self(self::CONVERSION_FAILED, 'Markdown conversion failed.', $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
