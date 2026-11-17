const assert = require('node:assert/strict');
const test = require('node:test');
const { activateWorkspace } = require('../../src/Editor/workspace-entry.js');

function run(overrides = {}) {
	const calls = [];
	activateWorkspace({
		active: true,
		isOpen: false,
		isDirty: false,
		postId: 42,
		closeSidebar: () => calls.push('close-sidebar'),
		openWorkspace: () => calls.push('open-workspace'),
		warn: (reason) => calls.push(reason),
		...overrides,
	});
	return calls;
}

test('selecting the plugin opens the workspace and closes its sidebar', () => {
	assert.deepEqual(run(), ['close-sidebar', 'open-workspace']);
});

test('unrelated sidebars do not trigger the workspace', () => {
	assert.deepEqual(run({ active: false }), []);
});

test('unsaved Gutenberg edits and missing post IDs block entry with a warning', () => {
	assert.deepEqual(run({ isDirty: true }), ['close-sidebar', 'dirty']);
	assert.deepEqual(run({ postId: 0 }), ['close-sidebar', 'missing-post']);
	assert.deepEqual(run({ postId: 'invalid' }), ['close-sidebar', 'missing-post']);
});

test('selecting the icon while the workspace is open preserves the session', () => {
	assert.deepEqual(run({ isOpen: true, isDirty: true }), ['close-sidebar']);
});
