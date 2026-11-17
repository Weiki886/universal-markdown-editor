<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Post;

use UniversalMarkdownEditor\Markdown\ConversionException;
use UniversalMarkdownEditor\Markdown\Converter;
use WP_Error;
use WP_Post;

final class SaveService
{
    private const SAVE_LOCK_TTL = 60;

    private MarkdownRepository $repository;
    private Converter $converter;
    private GutenbergImporter $importer;
    private PreservedBlockRenderer $blockRenderer;

    public function __construct(?MarkdownRepository $repository = null, ?Converter $converter = null)
    {
        $this->repository = $repository ?? new MarkdownRepository();
        $this->converter = $converter ?? new Converter();
        $this->importer = new GutenbergImporter();
        $this->blockRenderer = new PreservedBlockRenderer($this->converter);
    }

    /** @return array<string, mixed>|WP_Error */
    public function read(int $postId)
    {
        $post = $this->repository->getPost($postId);

        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error(
                'ume_post_not_found',
                __('The requested post does not exist or is not supported.', 'universal-markdown-editor'),
                ['status' => 404]
            );
        }

        $state = $this->repository->getState($postId);
        $hasMeta = $this->repository->hasAnyManagedMeta($state);
        $managed = ($state[MarkdownRepository::FORMAT] ?? null) === 'markdown';

        if (!$managed && $hasMeta) {
            return new WP_Error(
                'ume_unsupported_version',
                __('Markdown metadata is incomplete or unsupported. The post was not changed.', 'universal-markdown-editor'),
                ['status' => 422]
            );
        }

        if ($managed) {
            $version = filter_var($state[MarkdownRepository::CONVERTER_VERSION] ?? null, FILTER_VALIDATE_INT);
            $source = $state[MarkdownRepository::SOURCE] ?? null;
            $expectedContentHash = $state[MarkdownRepository::CONTENT_HASH] ?? null;
            $expectedSourceHash = $state[MarkdownRepository::SOURCE_HASH] ?? null;

            if (
                $version !== Converter::VERSION
                || !is_string($source)
                || preg_match('//u', $source) !== 1
                || !is_string($expectedContentHash)
                || preg_match('/^[a-f0-9]{64}$/', $expectedContentHash) !== 1
                || !is_string($expectedSourceHash)
                || preg_match('/^[a-f0-9]{64}$/', $expectedSourceHash) !== 1
            ) {
                return new WP_Error(
                    'ume_unsupported_version',
                    __('This post uses incomplete or unsupported Markdown metadata. It was not changed.', 'universal-markdown-editor'),
                    ['status' => 422]
                );
            }

            $actualSourceHash = hash('sha256', $source);
            $actualContentHash = hash('sha256', $post->post_content);

            if (!hash_equals($expectedSourceHash, $actualSourceHash) || !hash_equals($expectedContentHash, $actualContentHash)) {
                return new WP_Error(
                    'ume_content_inconsistent',
                    __('The saved Markdown and rendered content are inconsistent. Resolve the post before editing.', 'universal-markdown-editor'),
                    ['status' => 409]
                );
            }
        } else {
            $source = null;
            $version = null;
            $actualContentHash = hash('sha256', $post->post_content);
            $actualSourceHash = null;
        }

        $import = $managed ? null : $this->importer->import($post->post_content);
        $blocks = $managed ? $this->decodePreservedBlocks($state[MarkdownRepository::PRESERVED_BLOCKS] ?? '') : [];
        if ($managed && $blocks === null) {
            return new WP_Error(
                'ume_unsupported_version',
                __('Preserved Gutenberg block data is invalid. The post was not changed.', 'universal-markdown-editor'),
                ['status' => 422]
            );
        }

        return [
            'postId' => $postId,
            'managed' => $managed,
            'source' => $source,
            'html' => $post->post_content,
            'contentFormat' => $managed ? 'markdown' : null,
            'converterVersion' => $version,
            'contentHash' => $actualContentHash,
            'sourceHash' => $actualSourceHash,
            'modifiedGmt' => $post->post_modified_gmt,
            'revisionsEnabled' => wp_revisions_enabled($post),
            'importSource' => $import['source'] ?? null,
            'preservedBlockCount' => count($import['blocks'] ?? $blocks ?? []),
        ];
    }

    /** @return array<string, mixed>|WP_Error */
    public function preview(int $postId, string $source)
    {
        $session = $this->read($postId);
        if (is_wp_error($session)) {
            return $session;
        }
        $post = $this->repository->getPost($postId);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('ume_post_not_found', __('The requested post does not exist or is not supported.', 'universal-markdown-editor'), ['status' => 404]);
        }

        $state = $this->repository->getState($postId);
        $managed = $session['managed'];
        if ($managed) {
            $blocks = $this->decodePreservedBlocks($state[MarkdownRepository::PRESERVED_BLOCKS] ?? '');
            if ($blocks === null) {
                return new WP_Error('ume_unsupported_version', __('Preserved Gutenberg block data is invalid.', 'universal-markdown-editor'), ['status' => 422]);
            }
        } else {
            $import = $this->importer->import($post->post_content);
            $blocks = $import['blocks'] ?? [];
        }

        try {
            $html = $this->blockRenderer->render($source, $blocks);
        } catch (ConversionException $exception) {
            $invalidSource = $exception->errorCode() === ConversionException::INVALID_SOURCE;
            return new WP_Error(
                $invalidSource ? 'ume_invalid_source' : 'ume_parse_failed',
                __('Markdown could not be previewed safely.', 'universal-markdown-editor'),
                ['status' => $invalidSource ? 400 : 422]
            );
        }

        return ['html' => $html, 'converterVersion' => Converter::VERSION, 'sourceHash' => hash('sha256', $source)];
    }

    /** @return array<string, mixed>|WP_Error */
    public function save(int $postId, string $source, string $baseContentHash, string $baseModifiedGmt, ?string $baseSourceHash)
    {
        if (strlen($source) > Converter::MAX_SOURCE_BYTES) {
            return new WP_Error(
                'ume_source_too_large',
                __('Markdown source exceeds the 1 MiB limit.', 'universal-markdown-editor'),
                ['status' => 413]
            );
        }

        return $this->withPostLock(
            $postId,
            fn () => $this->saveLocked($postId, $source, $baseContentHash, $baseModifiedGmt, $baseSourceHash)
        );
    }

    /** @return array<string, mixed>|WP_Error */
    private function saveLocked(
        int $postId,
        string $source,
        string $baseContentHash,
        string $baseModifiedGmt,
        ?string $baseSourceHash
    )
    {
        $post = $this->repository->getPost($postId);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error(
                'ume_post_not_found',
                __('The requested post does not exist or is not supported.', 'universal-markdown-editor'),
                ['status' => 404]
            );
        }

        $currentHash = hash('sha256', $post->post_content);
        $currentModifiedGmt = $post->post_modified_gmt;
        $state = $this->repository->getState($postId);
        $managed = ($state[MarkdownRepository::FORMAT] ?? null) === 'markdown';
        $hasMeta = $this->repository->hasAnyManagedMeta($state);

        if (!$managed && $hasMeta) {
            return new WP_Error(
                'ume_unsupported_version',
                __('Markdown metadata is incomplete or unsupported. The post was not changed.', 'universal-markdown-editor'),
                ['status' => 422]
            );
        }

        if ($managed) {
            $currentSession = $this->read($postId);
            if (is_wp_error($currentSession)) {
                return $currentSession;
            }
        }

        $baselineChanged = !hash_equals($currentHash, $baseContentHash)
            || (!$managed && $currentModifiedGmt !== $baseModifiedGmt)
            || ($managed && (
                !is_string($state[MarkdownRepository::SOURCE] ?? null)
                || !is_string($baseSourceHash)
                || !hash_equals(hash('sha256', $state[MarkdownRepository::SOURCE]), $baseSourceHash)
            ));
        if ($baselineChanged) {
            return new WP_Error(
                'ume_edit_conflict',
                __('The post changed after this editing session started. Reload or copy your Markdown before saving again.', 'universal-markdown-editor'),
                ['status' => 409]
            );
        }

        $preservedBlocks = $managed
            ? $this->decodePreservedBlocks($state[MarkdownRepository::PRESERVED_BLOCKS] ?? '')
            : ($this->importer->import($post->post_content)['blocks'] ?? []);
        if ($preservedBlocks === null) {
            return new WP_Error('ume_unsupported_version', __('Preserved Gutenberg block data is invalid. The post was not changed.', 'universal-markdown-editor'), ['status' => 422]);
        }

        try {
            $html = $this->blockRenderer->render($source, $preservedBlocks);
        } catch (ConversionException $exception) {
            $invalidSource = $exception->errorCode() === ConversionException::INVALID_SOURCE;
            return new WP_Error(
                $invalidSource ? 'ume_invalid_source' : 'ume_parse_failed',
                $invalidSource ? __('Markdown source is invalid or its preserved block references were changed.', 'universal-markdown-editor') : __('Markdown could not be converted.', 'universal-markdown-editor'),
                ['status' => $invalidSource ? 400 : 422]
            );
        }

        $snapshot = $this->repository->snapshot($postId);
        if ($snapshot['post'] === []) {
            return new WP_Error(
                'ume_post_not_found',
                __('The requested post no longer exists.', 'universal-markdown-editor'),
                ['status' => 404]
            );
        }

        $revisionsEnabled = wp_revisions_enabled($post);
        if ($revisionsEnabled && $post->post_status !== 'auto-draft') {
            if (!$this->saveRevision($postId)) {
                return new WP_Error(
                    'ume_save_failed',
                    __('The current post state could not be secured in a WordPress revision. No content was changed.', 'universal-markdown-editor'),
                    ['status' => 500, 'restored' => true]
                );
            }
        }

        $postUpdate = [
            'ID' => $postId,
            'post_content' => wp_slash($html),
        ];
        if ($post->post_status === 'auto-draft') {
            $postUpdate['post_status'] = 'draft';
        }

        $revisionPriority = has_action('post_updated', 'wp_save_post_revision');
        if ($revisionPriority !== false) {
            remove_action('post_updated', 'wp_save_post_revision', $revisionPriority);
        }
        try {
            $updatedPostId = wp_update_post($postUpdate, true);
        } finally {
            if ($revisionPriority !== false) {
                add_action('post_updated', 'wp_save_post_revision', $revisionPriority, 3);
            }
        }
        if (is_wp_error($updatedPostId) || $updatedPostId === 0) {
            return $this->failAndRestore($postId, $snapshot, 'post_update');
        }

        $values = [
            MarkdownRepository::SOURCE => $source,
            MarkdownRepository::FORMAT => 'markdown',
            MarkdownRepository::CONVERTER_VERSION => Converter::VERSION,
            MarkdownRepository::CONTENT_HASH => hash('sha256', $html),
            MarkdownRepository::SOURCE_HASH => hash('sha256', $source),
            MarkdownRepository::PRESERVED_BLOCKS => wp_json_encode($preservedBlocks),
        ];

        if (!$this->repository->writeState($postId, $values)) {
            return $this->failAndRestore($postId, $snapshot, 'meta_write');
        }

        if ($revisionsEnabled) {
            if (!$this->saveRevision($postId)) {
                return $this->failAndRestore($postId, $snapshot, 'revision_write');
            }
        }

        $saved = $this->read($postId);
        if (
            is_wp_error($saved)
            || ($saved['source'] ?? null) !== $source
            || ($saved['html'] ?? null) !== $html
            || ($saved['contentFormat'] ?? null) !== 'markdown'
            || ($saved['converterVersion'] ?? null) !== Converter::VERSION
            || ($saved['contentHash'] ?? null) !== hash('sha256', $html)
            || ($saved['sourceHash'] ?? null) !== hash('sha256', $source)
        ) {
            return $this->failAndRestore($postId, $snapshot, 'read_back');
        }

        return $saved;
    }

    /** @return array<string, string>|null */
    private function decodePreservedBlocks($value): ?array
    {
        if ($value === '' || $value === null) {
            return [];
        }
        if (!is_string($value)) {
            return null;
        }

        $blocks = json_decode($value, true);
        if (!is_array($blocks)) {
            return null;
        }

        $normalized = [];
        foreach ($blocks as $key => $html) {
            if ((!is_int($key) && (!is_string($key) || preg_match('/^\d+$/', $key) !== 1)) || !is_string($html) || $html === '') {
                return null;
            }
            $normalized[(string) $key] = $html;
        }

        return $normalized;
    }

    private function saveRevision(int $postId): bool
    {
        $includeMarkdownMeta = static function (bool $changed, WP_Post $revision, WP_Post $post): bool {
            if ($changed) {
                return true;
            }

            foreach ([
                MarkdownRepository::SOURCE,
                MarkdownRepository::FORMAT,
                MarkdownRepository::CONVERTER_VERSION,
                MarkdownRepository::CONTENT_HASH,
                MarkdownRepository::SOURCE_HASH,
                MarkdownRepository::PRESERVED_BLOCKS,
            ] as $metaKey) {
                if (
                    get_post_meta($revision->ID, $metaKey, false)
                    !== get_post_meta($post->ID, $metaKey, false)
                ) {
                    return true;
                }
            }

            return false;
        };

        add_filter('wp_save_post_revision_post_has_changed', $includeMarkdownMeta, 10, 3);
        try {
            $revisionId = wp_save_post_revision($postId);
        } finally {
            remove_filter('wp_save_post_revision_post_has_changed', $includeMarkdownMeta, 10);
        }

        if (is_wp_error($revisionId) || $revisionId === 0 || $revisionId === false) {
            return false;
        }

        $revisions = wp_get_post_revisions($postId);
        $latestRevision = null;
        foreach ($revisions as $revision) {
            if (!$latestRevision instanceof WP_Post || $revision->ID > $latestRevision->ID) {
                $latestRevision = $revision;
            }
        }
        $post = $this->repository->getPost($postId);

        if (!$latestRevision instanceof WP_Post || !$post instanceof WP_Post || $latestRevision->post_content !== $post->post_content) {
            return false;
        }

        foreach ([
            MarkdownRepository::SOURCE,
            MarkdownRepository::FORMAT,
            MarkdownRepository::CONVERTER_VERSION,
            MarkdownRepository::CONTENT_HASH,
            MarkdownRepository::SOURCE_HASH,
            MarkdownRepository::PRESERVED_BLOCKS,
        ] as $metaKey) {
            if (get_post_meta($latestRevision->ID, $metaKey, false) !== get_post_meta($postId, $metaKey, false)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed>|WP_Error */
    private function withPostLock(int $postId, callable $callback)
    {
        $optionName = 'ume_markdown_save_lock_' . $postId;
        $token = wp_generate_uuid4();
        $lock = ['token' => $token, 'expires' => time() + self::SAVE_LOCK_TTL];

        if (!add_option($optionName, $lock, '', false)) {
            $currentLock = get_option($optionName);
            if (!is_array($currentLock) || (int) ($currentLock['expires'] ?? PHP_INT_MAX) > time()) {
                return new WP_Error(
                    'ume_edit_conflict',
                    __('Another Markdown save is in progress. Keep your source and retry after it finishes.', 'universal-markdown-editor'),
                    ['status' => 409]
                );
            }

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reclaiming an expired lock must atomically match both its name and serialized value; delete_option() cannot express that compare-and-delete.
            $wpdb->delete(
                $wpdb->options,
                ['option_name' => $optionName, 'option_value' => maybe_serialize($currentLock)],
                ['%s', '%s']
            );
            wp_cache_delete($optionName, 'options');
            wp_cache_delete('notoptions', 'options');

            if (!add_option($optionName, $lock, '', false)) {
                return new WP_Error(
                    'ume_edit_conflict',
                    __('Another Markdown save is in progress. Keep your source and retry after it finishes.', 'universal-markdown-editor'),
                    ['status' => 409]
                );
            }
        }

        try {
            return $callback();
        } finally {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Releasing the lock must match this request's token so a newer request's lock is never deleted.
            $wpdb->delete(
                $wpdb->options,
                ['option_name' => $optionName, 'option_value' => maybe_serialize($lock)],
                ['%s', '%s']
            );
            wp_cache_delete($optionName, 'options');
            wp_cache_delete('notoptions', 'options');
        }
    }

    /**
     * @param array{post: array<string, mixed>, meta: array<string, array<int, mixed>>} $snapshot
     * @return WP_Error
     */
    private function failAndRestore(int $postId, array $snapshot, string $stage): WP_Error
    {
        $restored = $this->repository->restore($postId, $snapshot);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The storage contract requires failure visibility; this message contains only a post ID, fixed stage, and restore status.
        error_log(SaveDiagnostics::failureMessage($postId, $stage, $restored));

        return new WP_Error(
            'ume_save_failed',
            $restored
                ? __('Markdown could not be saved. Previous post content and metadata were restored.', 'universal-markdown-editor')
                : __('Markdown could not be saved or safely restored. Stop editing this post and recover it from WordPress revisions.', 'universal-markdown-editor'),
            ['status' => 500, 'restored' => $restored]
        );
    }
}
