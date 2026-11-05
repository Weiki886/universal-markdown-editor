<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "The Markdown storage test must run inside WordPress.\n");
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
$adminIds = get_users([
    'role' => 'administrator',
    'number' => 1,
    'fields' => 'ID',
]);

if ($adminIds === []) {
    fwrite(STDERR, "Could not find an administrator for the Markdown storage test.\n");
    exit(1);
}

wp_set_current_user((int) $adminIds[0]);
$registeredMeta = get_registered_meta_keys('post');
foreach ([
    '_ume_markdown_source',
    '_ume_content_format',
    '_ume_converter_version',
    '_ume_content_hash',
    '_ume_saved_source_hash',
] as $metaKey) {
    $definition = $registeredMeta[$metaKey] ?? [];
    $check(($definition['single'] ?? false) === true, "{$metaKey} is not registered as a single value.");
    $check(($definition['show_in_rest'] ?? false) === false, "{$metaKey} is exposed through generic REST metadata.");
    $check(($definition['revisions_enabled'] ?? false) === true, "{$metaKey} is not included in post revisions.");
}

$existingId = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'draft',
    'post_title' => 'Markdown storage fixture',
    'post_content' => '<p>Existing HTML</p>',
    'post_author' => (int) $adminIds[0],
]);
$request = static function (string $method, int $postId, ?array $body = null): WP_REST_Response {
    $request = new WP_REST_Request($method, '/ume/v1/posts/' . $postId . '/markdown');

    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode($body));
    }

    return rest_do_request($request);
};
$unmanagedResponse = $request('GET', $existingId);
$unmanaged = $unmanagedResponse->get_data();
$check($unmanagedResponse->get_status() === 200, 'An authorized editor could not load a Markdown session.');
$check(is_array($unmanaged) && ($unmanaged['managed'] ?? null) === false, 'An ordinary post was reported as Markdown-managed.');
$check(is_array($unmanaged) && ($unmanaged['source'] ?? 'missing') === null, 'An ordinary post exposed a fabricated Markdown source.');
$check(is_array($unmanaged) && ($unmanaged['html'] ?? null) === '<p>Existing HTML</p>', 'The ordinary post HTML was not returned as the preview baseline.');
$check(is_array($unmanaged) && ($unmanaged['contentHash'] ?? null) === hash('sha256', '<p>Existing HTML</p>'), 'The unmanaged content hash is incorrect.');

$source = "# Saved\n\nOriginal **Markdown**; literal \\*markers\\*.\n";
$saveResponse = $request('PUT', $existingId, [
    'source' => $source,
    'baseContentHash' => $unmanaged['contentHash'] ?? '',
    'baseModifiedGmt' => $unmanaged['modifiedGmt'] ?? '',
]);
$saved = $saveResponse->get_data();
$expectedHtml = "<h1>Saved</h1>\n<p>Original <strong>Markdown</strong>; literal *markers*.</p>\n";
$check($saveResponse->get_status() === 200, 'An authorized post editor could not save Markdown.');
$check(is_array($saved) && ($saved['managed'] ?? null) === true, 'A successful save did not mark the post as managed.');
$check(is_array($saved) && ($saved['source'] ?? null) === $source, 'A successful save changed the Markdown source bytes.');
$check(is_array($saved) && ($saved['html'] ?? null) === $expectedHtml, 'A successful save did not return sanitized server-rendered HTML.');
$check(get_post_field('post_content', $existingId) === $expectedHtml, 'post_content does not contain the server-rendered HTML.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $source, 'The private Markdown source was not persisted.');
$check(get_post_meta($existingId, '_ume_content_format', true) === 'markdown', 'The content format marker was not persisted.');
$check((int) get_post_meta($existingId, '_ume_converter_version', true) === 1, 'The converter version was not persisted.');
$check(get_post_meta($existingId, '_ume_content_hash', true) === hash('sha256', $expectedHtml), 'The saved HTML hash is incorrect.');
$check(get_post_meta($existingId, '_ume_saved_source_hash', true) === hash('sha256', $source), 'The saved source hash is incorrect.');
$revisions = wp_get_post_revisions($existingId);
$matchingRevisionFound = false;
foreach ($revisions as $revision) {
    if (
        $revision->post_content === $expectedHtml
        && get_post_meta($revision->ID, '_ume_markdown_source', true) === $source
    ) {
        $matchingRevisionFound = true;
        break;
    }
}
$check($matchingRevisionFound, 'No revision contains the saved Markdown and its matching rendered HTML.');
$check(
    count(array_filter(wp_get_post_revisions($existingId), static fn ($revision): bool => $revision->post_content === $expectedHtml)) === 1,
    'The save created duplicate revisions for the same rendered content.'
);

$reloadResponse = $request('GET', $existingId);
$reloaded = $reloadResponse->get_data();
$check($reloadResponse->get_status() === 200 && ($reloaded['source'] ?? null) === $source, 'Reloading did not restore the exact Markdown source.');

$revisionCountBeforeSourceOnlySave = count(wp_get_post_revisions($existingId));
$sourceWithEquivalentOutput = $source . "\n";
$sourceOnlySaveResponse = $request('PUT', $existingId, [
    'source' => $sourceWithEquivalentOutput,
    'baseContentHash' => $saved['contentHash'] ?? '',
    'baseModifiedGmt' => $saved['modifiedGmt'] ?? '',
    'baseSourceHash' => $saved['sourceHash'] ?? null,
]);
$sourceOnlySaved = $sourceOnlySaveResponse->get_data();
$check($sourceOnlySaveResponse->get_status() === 200, 'A source-only Markdown change could not be saved.');
$check(($sourceOnlySaved['html'] ?? null) === $expectedHtml, 'Equivalent Markdown unexpectedly changed rendered HTML.');
$check(count(wp_get_post_revisions($existingId)) > $revisionCountBeforeSourceOnlySave, 'A source-only change did not create a revision.');
$sourceRevisionFound = false;
foreach (wp_get_post_revisions($existingId) as $revision) {
    if ($revision->post_content === $expectedHtml && get_post_meta($revision->ID, '_ume_markdown_source', true) === $sourceWithEquivalentOutput) {
        $sourceRevisionFound = true;
        break;
    }
}
$check($sourceRevisionFound, 'The source-only revision does not match its rendered HTML.');

$staleSourceResponse = $request('PUT', $existingId, [
    'source' => '# Stale Source Must Not Overwrite',
    'baseContentHash' => $saved['contentHash'] ?? '',
    'baseModifiedGmt' => $saved['modifiedGmt'] ?? '',
    'baseSourceHash' => $saved['sourceHash'] ?? null,
]);
$check($staleSourceResponse->get_status() === 409, 'A stale source baseline was not rejected when rendered HTML remained unchanged.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'A stale source baseline overwrote Markdown that renders to identical HTML.');

$conflictResponse = $request('PUT', $existingId, [
    'source' => '# Must Not Overwrite',
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
$check($conflictResponse->get_status() === 409, 'A stale content baseline did not return HTTP 409.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'A stale save overwrote the stored Markdown source.');
$check(get_post_field('post_content', $existingId) === $expectedHtml, 'A stale save overwrote post_content.');

$invalidSaveResponse = $request('PUT', $existingId, [
    'source' => ['not', 'a', 'string'],
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
$check($invalidSaveResponse->get_status() === 400, 'A non-string Markdown source was not rejected as invalid input.');
$oversizedSaveResponse = $request('PUT', $existingId, [
    'source' => str_repeat('x', 1048577),
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
$check($oversizedSaveResponse->get_status() === 413, 'A Markdown source above 1 MiB was not rejected with HTTP 413.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'Invalid input changed the stored Markdown source.');

$lockOption = 'ume_markdown_save_lock_' . $existingId;
add_option($lockOption, ['token' => 'another-request', 'expires' => time() + 30], '', false);
$lockedSaveResponse = $request('PUT', $existingId, [
    'source' => '# Concurrent Save',
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
$check($lockedSaveResponse->get_status() === 409, 'A simultaneous save was not rejected as a recoverable conflict.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'A request blocked by another save changed the Markdown source.');
delete_option($lockOption);

$writeFailureTriggered = false;
$postBeforeFailedSave = get_post($existingId);
$metaBeforeFailedSave = get_post_meta($existingId);
$failMetadataWrite = static function ($checkValue, int $objectId, string $metaKey) use ($existingId, &$writeFailureTriggered) {
    if ($objectId === $existingId && $metaKey === '_ume_saved_source_hash' && !$writeFailureTriggered) {
        $writeFailureTriggered = true;
        return false;
    }

    return $checkValue;
};
add_filter('update_post_metadata', $failMetadataWrite, 10, 3);
$failedSaveResponse = $request('PUT', $existingId, [
    'source' => '# Partial Write Must Roll Back',
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
remove_filter('update_post_metadata', $failMetadataWrite, 10);
$check($writeFailureTriggered, 'The metadata write failure fixture was not exercised.');
$check($failedSaveResponse->get_status() === 500, 'A failed metadata write did not return HTTP 500.');
$check(($failedSaveResponse->get_data()['data']['restored'] ?? false) === true, 'The failed metadata write did not report successful restoration.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'A failed save did not restore the previous Markdown source.');
$check(get_post_field('post_content', $existingId) === $expectedHtml, 'A failed save did not restore the previous rendered HTML.');
$check(get_post_meta($existingId) === $metaBeforeFailedSave, 'A failed save did not restore the complete plugin and post metadata snapshot.');
$check(get_post_field('post_modified_gmt', $existingId) === $postBeforeFailedSave->post_modified_gmt, 'A failed save changed the original post modified time.');

$autoDraftId = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'auto-draft',
    'post_title' => 'New Markdown post',
    'post_content' => '',
    'post_author' => (int) $adminIds[0],
]);
$autoDraftSession = $request('GET', $autoDraftId)->get_data();
$newSaveResponse = $request('PUT', $autoDraftId, [
    'source' => '',
    'baseContentHash' => $autoDraftSession['contentHash'] ?? '',
    'baseModifiedGmt' => $autoDraftSession['modifiedGmt'] ?? '',
    'baseSourceHash' => $autoDraftSession['sourceHash'] ?? null,
]);
$check($newSaveResponse->get_status() === 200, 'An empty Markdown document could not be saved for a new post.');
$check(get_post_status($autoDraftId) === 'draft', 'The first explicit save did not convert auto-draft to draft.');
$check(get_post_meta($autoDraftId, '_ume_markdown_source', true) === '', 'An empty Markdown source was not stored as a managed value.');

wp_set_current_user(0);
$anonymousRead = $request('GET', $existingId);
$check(in_array($anonymousRead->get_status(), [401, 403], true), 'An anonymous visitor could read private Markdown.');
$unauthorizedResponse = $request('PUT', $existingId, [
    'source' => '# Unauthorized',
    'baseContentHash' => $sourceOnlySaved['contentHash'] ?? '',
    'baseModifiedGmt' => $sourceOnlySaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $sourceOnlySaved['sourceHash'] ?? null,
]);
$check(in_array($unauthorizedResponse->get_status(), [401, 403], true), 'An anonymous visitor could save Markdown.');
$check(get_post_meta($existingId, '_ume_markdown_source', true) === $sourceWithEquivalentOutput, 'An anonymous request changed the stored Markdown source.');

wp_set_current_user((int) $adminIds[0]);
wp_delete_post($existingId, true);
wp_delete_post($autoDraftId, true);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    exit(1);
}

printf("Markdown storage integration test passed (%d assertions).\n", $assertions);
