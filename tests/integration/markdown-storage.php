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
$registeredMeta = get_registered_meta_keys('post', 'post');
foreach ([
    '_ume_markdown_source',
    '_ume_content_format',
    '_ume_converter_version',
    '_ume_content_hash',
    '_ume_saved_source_hash',
    '_ume_preserved_blocks',
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
$check(
    is_array($unmanaged)
        && array_key_exists('source', $unmanaged)
        && $unmanaged['source'] === null,
    'An ordinary post exposed a fabricated Markdown source.'
);
$check(is_array($unmanaged) && ($unmanaged['html'] ?? null) === '<p>Existing HTML</p>', 'The ordinary post HTML was not returned as the preview baseline.');
$check(is_array($unmanaged) && ($unmanaged['contentHash'] ?? null) === hash('sha256', '<p>Existing HTML</p>'), 'The unmanaged content hash is incorrect.');

$opaqueCallout = '<!-- wp:vendor/callout  { "tone" : "notice" } -->'
    . '<div class="callout">Keep this block</div><!-- /wp:vendor/callout -->';
$opaqueCard = '<!-- wp:vendor/card { "id" : "second" } -->'
    . '<div class="card">Keep second block<!-- wp:paragraph --><p>Nested content</p><!-- /wp:paragraph --></div>'
    . '<!-- /wp:vendor/card -->';
$opaqueDynamic = '<!-- wp:vendor/dynamic { "query" : "x" } /-->';
$gutenbergContent = '<!-- wp:paragraph --><p>Before block</p><!-- /wp:paragraph -->'
    . "\n\n"
    . $opaqueCallout
    . "\n\n"
    . $opaqueCard
    . "\n\n"
    . $opaqueDynamic
    . "\n\n"
    . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">After block</h2><!-- /wp:heading -->';
$gutenbergId = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'draft',
    'post_title' => 'Gutenberg preservation fixture',
    'post_content' => $gutenbergContent,
    'post_author' => (int) $adminIds[0],
]);
$gutenbergBefore = get_post($gutenbergId);
$gutenbergMetaBefore = get_post_meta($gutenbergId);
$gutenbergSessionResponse = $request('GET', $gutenbergId);
$gutenbergSession = $gutenbergSessionResponse->get_data();
$expectedImport = "Before block\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:1 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After block";
$check($gutenbergSessionResponse->get_status() === 200, 'A Gutenberg post could not be loaded for Markdown import.');
$check(($gutenbergSession['importSource'] ?? null) === $expectedImport, 'Supported blocks were not imported around an opaque block marker.');
$check(($gutenbergSession['preservedBlockCount'] ?? null) === 3, 'The opaque block count is incorrect.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergBefore->post_content, 'Opening a Gutenberg import changed post_content.');
$check(get_post_meta($gutenbergId) === $gutenbergMetaBefore, 'Opening a Gutenberg import changed post metadata.');

$previewRequest = new WP_REST_Request('POST', '/ume/v1/posts/' . $gutenbergId . '/markdown/preview');
$previewRequest->set_header('content-type', 'application/json');
$previewRequest->set_body(wp_json_encode(['source' => "Before edit\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:1 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After edit"]));
$gutenbergPreviewResponse = rest_do_request($previewRequest);
$gutenbergPreview = $gutenbergPreviewResponse->get_data();
$check($gutenbergPreviewResponse->get_status() === 200, 'A mixed Gutenberg/Markdown preview was rejected.');
$check(strpos($gutenbergPreview['html'] ?? '', $opaqueCallout) !== false, 'The preview did not restore the exact opaque block serialization.');
$check(strpos($gutenbergPreview['html'] ?? '', $opaqueCard) !== false, 'The preview did not restore the exact nested opaque block serialization.');
$check(strpos($gutenbergPreview['html'] ?? '', $opaqueDynamic) !== false, 'The preview did not restore the exact self-closing block serialization.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergBefore->post_content, 'Previewing a mixed post changed post_content.');
$check(get_post_meta($gutenbergId) === $gutenbergMetaBefore, 'Previewing a mixed post changed post metadata.');

$revisionsBeforeRejectedSave = count(wp_get_post_revisions($gutenbergId));
$invalidGutenbergSave = $request('PUT', $gutenbergId, [
    'source' => 'Before edit' . "\n\n" . '## After edit',
    'baseContentHash' => $gutenbergSession['contentHash'] ?? '',
    'baseModifiedGmt' => $gutenbergSession['modifiedGmt'] ?? '',
]);
$check($invalidGutenbergSave->get_status() === 400, 'A save that omitted a protected block marker was accepted.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergBefore->post_content, 'A rejected Gutenberg save changed post_content.');
$check(get_post_meta($gutenbergId) === $gutenbergMetaBefore, 'A rejected Gutenberg save changed post metadata.');
$check(count(wp_get_post_revisions($gutenbergId)) === $revisionsBeforeRejectedSave, 'A rejected Gutenberg save created a revision.');

$validGutenbergSource = "Before edit\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:1 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After edit";
$validGutenbergSave = $request('PUT', $gutenbergId, [
    'source' => $validGutenbergSource,
    'baseContentHash' => $gutenbergSession['contentHash'] ?? '',
    'baseModifiedGmt' => $gutenbergSession['modifiedGmt'] ?? '',
]);
$validGutenbergSaved = $validGutenbergSave->get_data();
$gutenbergSavedContent = get_post_field('post_content', $gutenbergId);
$check($validGutenbergSave->get_status() === 200, 'A valid mixed Gutenberg/Markdown save failed.');
$check(strpos($gutenbergSavedContent, $opaqueCallout) !== false, 'Saving Markdown did not preserve the exact opaque block serialization.');
$check(strpos($gutenbergSavedContent, $opaqueCard) !== false, 'Saving Markdown did not preserve the exact nested opaque block serialization.');
$check(strpos($gutenbergSavedContent, $opaqueDynamic) !== false, 'Saving Markdown did not preserve the exact self-closing block serialization.');
$check(strpos($gutenbergSavedContent, '<p>Before edit</p>') < strpos($gutenbergSavedContent, '<!-- wp:vendor/callout'), 'The opaque block moved before its preceding Markdown.');
$check(strpos($gutenbergSavedContent, '<!-- wp:vendor/callout') < strpos($gutenbergSavedContent, '<h2>After edit</h2>'), 'The opaque block moved after its following Markdown.');
$check(($validGutenbergSaved['source'] ?? null) === $validGutenbergSource, 'The mixed Markdown source was not stored exactly.');
$storedBlocks = json_decode((string) get_post_meta($gutenbergId, '_ume_preserved_blocks', true), true);
$check(is_array($storedBlocks) && count($storedBlocks) === 3, 'Protected block data was not stored in private metadata.');
$gutenbergRevision = null;
foreach (wp_get_post_revisions($gutenbergId) as $revision) {
    if ($revision->post_content === $gutenbergSavedContent) {
        $gutenbergRevision = $revision;
        break;
    }
}
$check(
    $gutenbergRevision instanceof WP_Post
        && get_post_meta($gutenbergRevision->ID, '_ume_preserved_blocks', true) === get_post_meta($gutenbergId, '_ume_preserved_blocks', true),
    'The protected block mapping was not included in the matching WordPress revision.'
);

$revisionsBeforeTampering = count(wp_get_post_revisions($gutenbergId));
$metadataBeforeTampering = get_post_meta($gutenbergId);
$tamperedGutenbergSave = $request('PUT', $gutenbergId, [
    'source' => "Before edit\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After edit",
    'baseContentHash' => $validGutenbergSaved['contentHash'] ?? '',
    'baseModifiedGmt' => $validGutenbergSaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $validGutenbergSaved['sourceHash'] ?? null,
]);
$check($tamperedGutenbergSave->get_status() === 400, 'A duplicated opaque block marker was accepted.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergSavedContent, 'A rejected tampered save changed post_content.');
$check(get_post_meta($gutenbergId) === $metadataBeforeTampering, 'A rejected tampered save changed post metadata.');
$check(count(wp_get_post_revisions($gutenbergId)) === $revisionsBeforeTampering, 'A rejected tampered save created a revision.');
$reorderedGutenbergSave = $request('PUT', $gutenbergId, [
    'source' => "Before edit\n\n<!-- ume-preserved-block:1 -->\n\n<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After edit",
    'baseContentHash' => $validGutenbergSaved['contentHash'] ?? '',
    'baseModifiedGmt' => $validGutenbergSaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $validGutenbergSaved['sourceHash'] ?? null,
]);
$check($reorderedGutenbergSave->get_status() === 400, 'Reordered opaque block markers were accepted.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergSavedContent, 'A rejected reordered save changed post_content.');
$check(count(wp_get_post_revisions($gutenbergId)) === $revisionsBeforeTampering, 'A rejected reordered save created a revision.');
$inlineGutenbergSave = $request('PUT', $gutenbergId, [
    'source' => "Before edit <!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:1 -->\n\n<!-- ume-preserved-block:2 -->\n\n## After edit",
    'baseContentHash' => $validGutenbergSaved['contentHash'] ?? '',
    'baseModifiedGmt' => $validGutenbergSaved['modifiedGmt'] ?? '',
    'baseSourceHash' => $validGutenbergSaved['sourceHash'] ?? null,
]);
$check($inlineGutenbergSave->get_status() === 400, 'An inline opaque block marker was accepted.');
$check(get_post_field('post_content', $gutenbergId) === $gutenbergSavedContent, 'A rejected inline-marker save changed post_content.');
$check(get_post_meta($gutenbergId) === $metadataBeforeTampering, 'A rejected inline-marker save changed post metadata.');
$check(count(wp_get_post_revisions($gutenbergId)) === $revisionsBeforeTampering, 'A rejected inline-marker save created a revision.');

wp_delete_post($gutenbergId, true);

$lockOption = 'ume_markdown_save_lock_' . $existingId;
add_option($lockOption, ['token' => 'expired-request', 'expires' => time() - 1], '', false);

$source = "# Saved\n\nOriginal **Markdown**; literal \\*markers\\*.\n";
$saveResponse = $request('PUT', $existingId, [
    'source' => $source,
    'baseContentHash' => $unmanaged['contentHash'] ?? '',
    'baseModifiedGmt' => $unmanaged['modifiedGmt'] ?? '',
]);
$saved = $saveResponse->get_data();
$expectedHtml = "<h1>Saved</h1>\n<p>Original <strong>Markdown</strong>; literal *markers*.</p>\n";
$check(
    $saveResponse->get_status() === 200,
    'An authorized post editor could not save Markdown: HTTP '
        . $saveResponse->get_status() . ' '
        . wp_json_encode([
            'response' => $saved,
            'baseline' => [
                'contentHash' => $unmanaged['contentHash'] ?? null,
                'modifiedGmt' => $unmanaged['modifiedGmt'] ?? null,
            ],
        ])
);
$check(is_array($saved) && ($saved['managed'] ?? null) === true, 'A successful save did not mark the post as managed.');
$check(is_array($saved) && ($saved['source'] ?? null) === $source, 'A successful save changed the Markdown source bytes.');
$check(is_array($saved) && ($saved['html'] ?? null) === $expectedHtml, 'A successful save did not return sanitized server-rendered HTML.');
$check(get_option($lockOption, null) === null, 'A successful save did not release its per-post save lock.');
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
        && get_post_meta($revision->ID, '_ume_preserved_blocks', true) === '[]'
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
    'baseContentHash' => hash('sha256', 'stale rendered content baseline'),
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
