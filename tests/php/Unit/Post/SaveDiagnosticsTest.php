<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Tests\Unit\Post;

use PHPUnit\Framework\TestCase;
use UniversalMarkdownEditor\Post\SaveDiagnostics;

final class SaveDiagnosticsTest extends TestCase
{
    public function testFailureMessageContainsOnlyOperationalIdentifiers(): void
    {
        self::assertSame(
            'Universal Markdown Editor save failed for post 42 during meta_write; restore=ok.',
            SaveDiagnostics::failureMessage(42, 'meta_write', true)
        );
    }

    public function testUnrecognizedStageCannotInjectPrivateContentIntoTheMessage(): void
    {
        self::assertSame(
            'Universal Markdown Editor save failed for post 42 during unknown; restore=failed.',
            SaveDiagnostics::failureMessage(42, 'meta_write; private Markdown', false)
        );
    }
}
