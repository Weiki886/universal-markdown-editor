<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Post;

final class SaveDiagnostics
{
    public static function failureMessage(int $postId, string $stage, bool $restored): string
    {
        $safeStage = preg_match('/^[a-z][a-z0-9_]*$/', $stage) === 1 ? $stage : 'unknown';

        return sprintf(
            'Universal Markdown Editor save failed for post %d during %s; restore=%s.',
            $postId,
            $safeStage,
            $restored ? 'ok' : 'failed'
        );
    }
}
