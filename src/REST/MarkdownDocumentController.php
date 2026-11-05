<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\REST;

use UniversalMarkdownEditor\Post\SaveService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MarkdownDocumentController
{
    private const REST_NAMESPACE = 'ume/v1';

    public static function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/posts/(?P<id>\d+)/markdown',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'read'],
                    'permission_callback' => [MarkdownPreviewController::class, 'canEditPost'],
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'save'],
                    'permission_callback' => [MarkdownPreviewController::class, 'canEditPost'],
                ],
            ]
        );
    }

    public static function read(WP_REST_Request $request)
    {
        $result = (new SaveService())->read((int) $request['id']);

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public static function save(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return self::invalidRequest();
        }

        $source = $body['source'] ?? null;
        $baseContentHash = $body['baseContentHash'] ?? null;
        $baseModifiedGmt = $body['baseModifiedGmt'] ?? null;
        $baseSourceHash = $body['baseSourceHash'] ?? null;

        if (
            !is_string($source)
            || !is_string($baseContentHash)
            || preg_match('/^[a-f0-9]{64}$/', $baseContentHash) !== 1
            || !is_string($baseModifiedGmt)
            || $baseModifiedGmt === ''
            || ($baseSourceHash !== null && (!is_string($baseSourceHash) || preg_match('/^[a-f0-9]{64}$/', $baseSourceHash) !== 1))
        ) {
            return self::invalidRequest();
        }

        $result = (new SaveService())->save(
            (int) $request['id'],
            $source,
            $baseContentHash,
            $baseModifiedGmt,
            $baseSourceHash
        );

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    private static function invalidRequest(): WP_Error
    {
        return new WP_Error(
            'ume_invalid_source',
            'The request must contain a Markdown source string and a valid editing baseline.',
            ['status' => 400]
        );
    }
}
