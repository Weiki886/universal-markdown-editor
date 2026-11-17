const assert = require('node:assert/strict');
const test = require('node:test');
const {
	importBlocksToMarkdown,
	prepareGutenbergImport,
} = require('../../src/Editor/gutenberg-import.js');

test('imports plain paragraphs and headings as Markdown', () => {
	const result = importBlocksToMarkdown([
		{
			blockName: 'core/heading',
			attrs: { level: 2 },
			innerBlocks: [],
			innerHTML: '<h2 class="wp-block-heading">A title</h2>',
		},
		{
			blockName: 'core/paragraph',
			attrs: {},
			innerBlocks: [],
			innerHTML: '<p>Body text.</p>',
		},
	]);

	assert.deepEqual(result, {
		source: '## A title\n\nBody text.',
		preservedCount: 0,
	});
});

test('prepares a verified Gutenberg import for immediate editing', () => {
	const blocks = [
		{
			blockName: 'core/paragraph',
			attrs: {},
			innerBlocks: [],
			innerHTML: '<p>Existing text</p>',
		},
		{
			blockName: 'core/image',
			attrs: { id: 42 },
			innerBlocks: [],
			innerHTML: '<figure><img src="/image.jpg" alt="Photo"></figure>',
		},
	];
	const expectedSource =
		'Existing text\n\n<!-- ume-preserved-block:0 -->';

	assert.deepEqual(
		prepareGutenbergImport( blocks, expectedSource ),
		{ source: expectedSource, preservedCount: 1 }
	);
});

test('rejects Gutenberg imports that do not match the server result', () => {
	assert.throws(
		() => prepareGutenbergImport( [], 'unexpected source' ),
		/Gutenberg import results did not match/
	);
});

test('keeps unsupported blocks in their original order', () => {
	const result = importBlocksToMarkdown([
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p>Before</p>' },
		{ blockName: 'core/image', attrs: { id: 42 }, innerBlocks: [], innerHTML: '<figure><img src="/image.jpg" alt="Photo"></figure>' },
		{ blockName: 'vendor/interactive-card', attrs: { content: 'state' }, innerBlocks: [], innerHTML: '<div>Interactive</div>' },
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p>After</p>' },
	]);

	assert.equal(result.source, [
		'Before',
		'',
		'<!-- ume-preserved-block:0 -->',
		'',
		'<!-- ume-preserved-block:1 -->',
		'',
		'After',
	].join('\n'));
	assert.equal(result.preservedCount, 2);
});

test('ignores whitespace-only freeform blocks between Gutenberg blocks', () => {
	const result = importBlocksToMarkdown([
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p>Before</p>' },
		{ blockName: null, attrs: {}, innerBlocks: [], innerHTML: '\n\n' },
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p>After</p>' },
	]);

	assert.equal(result.source, 'Before\n\nAfter');
	assert.equal(result.preservedCount, 0);
});

test('preserves non-empty freeform blocks', () => {
	const result = importBlocksToMarkdown([
		{ blockName: null, attrs: {}, innerBlocks: [], innerHTML: '<div>Keep this</div>' },
	]);

	assert.equal(result.source, '<!-- ume-preserved-block:0 -->');
	assert.equal(result.preservedCount, 1);
});

test('preserves styled and rich-text paragraphs instead of flattening them', () => {
	const result = importBlocksToMarkdown([
		{ blockName: 'core/paragraph', attrs: { textColor: 'vivid-red' }, innerBlocks: [], innerHTML: '<p class="has-vivid-red-color">Styled</p>' },
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p><strong>Bold</strong></p>' },
	]);

	assert.equal(result.source, '<!-- ume-preserved-block:0 -->\n\n<!-- ume-preserved-block:1 -->');
	assert.equal(result.preservedCount, 2);
});

test('preserves blocks with nested children as a single opaque block', () => {
	const result = importBlocksToMarkdown([
		{
			blockName: 'core/group',
			attrs: {},
			innerBlocks: [
				{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '<p>Nested</p>' },
			],
			innerHTML: '',
		},
	]);

	assert.equal(result.source, '<!-- ume-preserved-block:0 -->');
	assert.equal(result.preservedCount, 1);
});

// Match the raw parser contract used by PHP parse_blocks and WordPress JS.
test('imports raw parser output including separators and an empty paragraph', () => {
	const blocks = [
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '\n<p>Existing text</p>\n', innerContent: ['\n<p>Existing text</p>\n'] },
		{ blockName: null, attrs: {}, innerBlocks: [], innerHTML: '\n\n', innerContent: ['\n\n'] },
		{ blockName: 'core/paragraph', attrs: {}, innerBlocks: [], innerHTML: '\n<p></p>\n', innerContent: ['\n<p></p>\n'] },
	];
	assert.deepEqual(prepareGutenbergImport(blocks, 'Existing text\n\n'), {
		source: 'Existing text\n\n', preservedCount: 0,
	});
});

test('uses the heading tag when the serialized level attribute is absent', () => {
	assert.deepEqual(prepareGutenbergImport([
		{ blockName: 'core/heading', attrs: null, innerBlocks: [], innerHTML: '<h1>A title</h1>' },
	], '# A title'), { source: '# A title', preservedCount: 0 });
});

test('preserves a heading whose tag and serialized level disagree', () => {
	assert.deepEqual(importBlocksToMarkdown([
		{ blockName: 'core/heading', attrs: { level: 3 }, innerBlocks: [], innerHTML: '<h2>A title</h2>' },
	]), { source: '<!-- ume-preserved-block:0 -->', preservedCount: 1 });
});

test('rejects normalized editor blocks rather than importing a different contract', () => {
	assert.throws(() => importBlocksToMarkdown([
		{ name: 'core/paragraph', attributes: { content: 'Existing text', dropCap: false }, innerBlocks: [], originalContent: '<p>Existing text</p>' },
	]), /raw parser/);
});
