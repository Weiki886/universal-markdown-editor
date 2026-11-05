<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\REST;

use UniversalMarkdownEditor\Markdown\ConversionException;
use UniversalMarkdownEditor\Markdown\Converter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MarkdownPreviewController
{
    private const REST_NAMESPACE = 'ume/v1';

    public static function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/posts/(?P<id>\d+)/markdown/preview',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'preview'],
                'permission_callback' => [self::class, 'canEditPost'],
            ]
        );
    }

    public static function canEditPost(WP_REST_Request $request)
    {
        $post = get_post((int) $request['id']);

        if (!$post instanceof \WP_Post || $post->post_type !== 'post') {
            return new WP_Error(
                'ume_post_not_found',
                'The requested post does not exist or is not supported.',
                ['status' => 404]
            );
        }

        if (!current_user_can('edit_post', $post->ID)) {
            $status = is_user_logged_in() ? 403 : 401;

            return new WP_Error(
                'rest_forbidden',
                'You are not allowed to preview this post.',
                ['status' => $status]
            );
        }

        return true;
    }

    public static function preview(WP_REST_Request $request)
    {
        $source = $request->get_param('source');

        if (!is_string($source)) {
            return new WP_Error(
                'ume_invalid_source',
                'Markdown source must be a string.',
                ['status' => 400]
            );
        }

        if (strlen($source) > Converter::MAX_SOURCE_BYTES) {
            return new WP_Error(
                'ume_source_too_large',
                'Markdown source exceeds the 1 MiB limit.',
                ['status' => 413]
            );
        }

        try {
            $html = (new Converter())->convert($source);
        } catch (ConversionException $exception) {
            $invalidSource = $exception->errorCode() === ConversionException::INVALID_SOURCE;

            return new WP_Error(
                $invalidSource ? 'ume_invalid_source' : 'ume_parse_failed',
                $invalidSource ? 'Markdown source is invalid.' : 'Markdown could not be converted.',
                ['status' => $invalidSource ? 400 : 422]
            );
        }

        return new WP_REST_Response(
            [
                'html' => $html,
                'converterVersion' => Converter::VERSION,
                'sourceHash' => hash('sha256', $source),
            ],
            200
        );
    }
}
