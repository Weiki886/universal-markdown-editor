<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Post;

use WP_Post;

final class MarkdownRepository
{
    public const SOURCE = '_ume_markdown_source';
    public const FORMAT = '_ume_content_format';
    public const CONVERTER_VERSION = '_ume_converter_version';
    public const CONTENT_HASH = '_ume_content_hash';
    public const SOURCE_HASH = '_ume_saved_source_hash';

    /** @var string[] */
    private const META_KEYS = [
        self::SOURCE,
        self::FORMAT,
        self::CONVERTER_VERSION,
        self::CONTENT_HASH,
        self::SOURCE_HASH,
    ];

    public static function registerMeta(): void
    {
        $definitions = [
            self::SOURCE => 'string',
            self::FORMAT => 'string',
            self::CONVERTER_VERSION => 'integer',
            self::CONTENT_HASH => 'string',
            self::SOURCE_HASH => 'string',
        ];

        foreach ($definitions as $key => $type) {
            register_post_meta(
                'post',
                $key,
                [
                    'type' => $type,
                    'single' => true,
                    'show_in_rest' => false,
                    'revisions_enabled' => true,
                ]
            );
        }
    }

    public function getPost(int $postId): ?WP_Post
    {
        $post = get_post($postId);

        return $post instanceof WP_Post ? $post : null;
    }

    /** @return array<string, mixed> */
    public function getState(int $postId): array
    {
        $state = [];

        foreach (self::META_KEYS as $key) {
            $state[$key] = get_post_meta($postId, $key, true);
            $state[$key . '_exists'] = metadata_exists('post', $postId, $key);
        }

        return $state;
    }

    /** @return array{post: array<string, mixed>, meta: array<string, array<int, mixed>>} */
    public function snapshot(int $postId): array
    {
        $post = $this->getPost($postId);

        if (!$post instanceof WP_Post) {
            return ['post' => [], 'meta' => []];
        }

        $meta = [];
        foreach (self::META_KEYS as $key) {
            $meta[$key] = get_post_meta($postId, $key, false);
        }

        return [
            'post' => [
                'post_content' => $post->post_content,
                'post_status' => $post->post_status,
                'post_modified' => $post->post_modified,
                'post_modified_gmt' => $post->post_modified_gmt,
            ],
            'meta' => $meta,
        ];
    }

    /** @param array<string, mixed> $values */
    public function writeState(int $postId, array $values): bool
    {
        foreach ($values as $key => $value) {
            if (!in_array($key, self::META_KEYS, true)) {
                return false;
            }

            update_post_meta($postId, $key, is_string($value) ? wp_slash($value) : $value);

            if ((string) get_post_meta($postId, $key, true) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array{post: array<string, mixed>, meta: array<string, array<int, mixed>>} $snapshot */
    public function restore(int $postId, array $snapshot): bool
    {
        if (($snapshot['post'] ?? []) === []) {
            return false;
        }

        foreach (self::META_KEYS as $key) {
            delete_post_meta($postId, $key);
            foreach ($snapshot['meta'][$key] ?? [] as $value) {
                add_post_meta($postId, $key, is_string($value) ? wp_slash($value) : $value, false);
            }
        }

        $postResult = wp_update_post(
            [
                'ID' => $postId,
                'post_content' => wp_slash($snapshot['post']['post_content']),
                'post_status' => $snapshot['post']['post_status'],
                'post_modified' => $snapshot['post']['post_modified'],
                'post_modified_gmt' => $snapshot['post']['post_modified_gmt'],
            ],
            true
        );

        if (is_wp_error($postResult)) {
            return false;
        }

        global $wpdb;
        $restoredDates = $wpdb->update(
            $wpdb->posts,
            [
                'post_modified' => $snapshot['post']['post_modified'],
                'post_modified_gmt' => $snapshot['post']['post_modified_gmt'],
            ],
            ['ID' => $postId],
            ['%s', '%s'],
            ['%d']
        );

        if ($restoredDates === false) {
            return false;
        }

        clean_post_cache($postId);
        $post = $this->getPost($postId);
        if (!$post instanceof WP_Post) {
            return false;
        }

        if (
            $post->post_content !== $snapshot['post']['post_content']
            || $post->post_status !== $snapshot['post']['post_status']
            || $post->post_modified !== $snapshot['post']['post_modified']
            || $post->post_modified_gmt !== $snapshot['post']['post_modified_gmt']
        ) {
            return false;
        }

        foreach (self::META_KEYS as $key) {
            if (get_post_meta($postId, $key, false) !== ($snapshot['meta'][$key] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $state */
    public function hasAnyManagedMeta(array $state): bool
    {
        foreach (self::META_KEYS as $key) {
            if ($state[$key . '_exists'] ?? false) {
                return true;
            }
        }

        return false;
    }
}
