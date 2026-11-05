<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "The Markdown preview test must run inside WordPress.\n");
    exit(1);
}

$failures = [];
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    ++$assertions;

    if (!$condition) {
        $failures[] = $message;
    }
};
$adminId = get_current_user_id();
$postId = wp_insert_post(
    [
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_title' => 'Markdown preview integration fixture',
        'post_content' => 'Existing content must remain unchanged.',
        'post_author' => $adminId,
    ],
    true
);

if (is_wp_error($postId)) {
    fwrite(STDERR, 'Could not create preview integration post: ' . $postId->get_error_message() . "\n");
    exit(1);
}

$postBefore = get_post($postId);
$metaBefore = get_post_meta($postId);
$source = "# Preview\n\n**Server-rendered**";
$request = new WP_REST_Request('POST', '/ume/v1/posts/' . $postId . '/markdown/preview');
$request->set_header('content-type', 'application/json');
$request->set_body(wp_json_encode(['source' => $source]));
$response = rest_do_request($request);
$data = $response->get_data();

$check($response->get_status() === 200, 'An authorized post editor could not request a Markdown preview.');
$check(is_array($data) && ($data['html'] ?? null) === "<h1>Preview</h1>\n<p><strong>Server-rendered</strong></p>\n", 'The preview did not return sanitized server-rendered HTML.');
$check(is_array($data) && ($data['converterVersion'] ?? null) === 1, 'The preview did not return the converter version.');
$check(is_array($data) && ($data['sourceHash'] ?? null) === hash('sha256', $source), 'The preview source hash is incorrect.');
$check(get_post_field('post_content', $postId) === $postBefore->post_content, 'Preview changed post_content.');
$check(get_post_meta($postId) === $metaBefore, 'Preview changed post metadata.');
$check(get_post_field('post_modified_gmt', $postId) === $postBefore->post_modified_gmt, 'Preview changed the post modified time.');

$emptyRequest = new WP_REST_Request('POST', '/ume/v1/posts/' . $postId . '/markdown/preview');
$emptyRequest->set_body(wp_json_encode(['source' => '']));
$emptyResponse = rest_do_request($emptyRequest);
$check($emptyResponse->get_status() === 200 && ($emptyResponse->get_data()['html'] ?? null) === '', 'An empty Markdown source was rejected.');

$invalidRequest = new WP_REST_Request('POST', '/ume/v1/posts/' . $postId . '/markdown/preview');
$invalidRequest->set_body(wp_json_encode(['source' => ['not', 'a', 'string']]));
$invalidResponse = rest_do_request($invalidRequest);
$check($invalidResponse->get_status() === 400, 'A non-string Markdown source was not rejected as invalid input.');

$oversizedRequest = new WP_REST_Request('POST', '/ume/v1/posts/' . $postId . '/markdown/preview');
$oversizedRequest->set_body(wp_json_encode(['source' => str_repeat('x', 1048577)]));
$oversizedResponse = rest_do_request($oversizedRequest);
$check($oversizedResponse->get_status() === 413, 'Markdown source above 1 MiB was not rejected with HTTP 413.');

wp_set_current_user(0);
$unauthorizedRequest = new WP_REST_Request('POST', '/ume/v1/posts/' . $postId . '/markdown/preview');
$unauthorizedRequest->set_body(wp_json_encode(['source' => '# Unauthorized']));
$unauthorizedResponse = rest_do_request($unauthorizedRequest);
$check(in_array($unauthorizedResponse->get_status(), [401, 403], true), 'An unauthenticated visitor could preview a post.');
$check(get_post_field('post_content', $postId) === $postBefore->post_content, 'An unauthorized preview changed post_content.');

wp_set_current_user($adminId);
wp_delete_post($postId, true);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    exit(1);
}

printf("Markdown preview integration test passed (%d assertions).\n", $assertions);
