const assert = require('node:assert/strict');
const test = require('node:test');

const { isComplexContent } = require('../../src/Editor/source-classification.js');

test('Markdown links remain eligible for plain-text import', () => {
	assert.equal(isComplexContent('Read [the guide](https://example.com).'), false);
});

test('Gutenberg blocks, HTML, and shortcodes require an import warning', () => {
	assert.equal(isComplexContent('<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'), true);
	assert.equal(isComplexContent('<p>HTML content</p>'), true);
	assert.equal(isComplexContent('[gallery ids="1,2"]'), true);
});

test('ordinary Markdown syntax remains eligible for plain-text import', () => {
	assert.equal(isComplexContent('# Heading\n\n**bold** and `code`'), false);
});
