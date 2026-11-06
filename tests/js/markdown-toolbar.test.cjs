const assert = require('node:assert/strict');
const test = require('node:test');

const {
	applyMarkdownAction,
	buildMarkdownImage,
	buildMarkdownLink,
	isSafeImageUrl,
	isSafeLinkUrl,
} = require('../../src/Editor/markdown-toolbar.js');

test('formatting preserves selected text exactly once and returns its new selection', () => {
	const result = applyMarkdownAction('你好 world', 3, 8, 'bold');

	assert.deepEqual(result, {
		source: '你好 **world**',
		selectionStart: 5,
		selectionEnd: 10,
	});
});

test('formatting without a selection inserts a usable placeholder', () => {
	const result = applyMarkdownAction('', 0, 0, 'link');

	assert.deepEqual(result, {
		source: '[link text](https://example.com)',
		selectionStart: 1,
		selectionEnd: 10,
	});
});

test('block formatting preserves the selected text and line boundaries', () => {
	const result = applyMarkdownAction('before\ntext\nafter', 7, 11, 'quote');

	assert.equal(result.source, 'before\n> text\nafter');
});

test('link URLs allow web, mail, telephone and relative references but reject executable protocols', () => {
	for (const url of ['https://example.com/a', 'mailto:a@example.com', 'tel:+123', '/about', '#section', '../page']) {
		assert.equal(isSafeLinkUrl(url), true, url);
	}

	for (const url of ['javascript:alert(1)', 'data:text/html,hello', 'vbscript:msgbox(1)', 'file:///etc/passwd', '//evil.example/path']) {
		assert.equal(isSafeLinkUrl(url), false, url);
	}
});

test('media URLs must be absolute HTTP(S) URLs and never blob or data URLs', () => {
	assert.equal(isSafeImageUrl('https://example.com/wp-content/image.jpg'), true);
	assert.equal(isSafeImageUrl('http://example.com/image.jpg'), true);
	assert.equal(isSafeImageUrl('/uploads/image.jpg'), false);
	assert.equal(isSafeImageUrl('blob:https://example.com/id'), false);
	assert.equal(isSafeImageUrl('data:image/png;base64,AAAA'), false);
});

test('link and image Markdown escape labels and encode destination delimiters', () => {
	assert.equal(buildMarkdownLink('[guide]', 'https://example.com/a b'), '[\\[guide\\]](https://example.com/a%20b)');
	assert.equal(buildMarkdownImage('a [photo]', 'https://example.com/a(b).jpg'), '![a \\[photo\\]](https://example.com/a%28b%29.jpg)');
});

test('link and image labels cannot inject Markdown syntax across lines', () => {
	assert.equal(buildMarkdownLink('one\\[two]\n[next](javascript:1)', 'https://example.com'), '[one\\\\\\[two\\] \\[next\\](javascript:1)](https://example.com)');
	assert.equal(buildMarkdownImage('first\n![fake](javascript:1)', 'https://example.com/image.jpg'), '![first !\\[fake\\](javascript:1)](https://example.com/image.jpg)');
});
