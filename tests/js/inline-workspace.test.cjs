const assert = require('node:assert/strict');
const test = require('node:test');

const {
	activateInlineWorkspaceHost,
	findInlineWorkspaceHost,
} = require('../../src/Editor/inline-workspace.js');

test('finds the Gutenberg content host before the visual editor fallback', () => {
	const contentHost = { id: 'content' };
	const visualEditor = { id: 'visual' };
	const documentNode = {
		querySelector(selector) {
			if (selector === '.interface-interface-skeleton__content') {
				return contentHost;
			}
			if (selector === '.editor-visual-editor') {
				return visualEditor;
			}
			return null;
		},
	};

	assert.equal(findInlineWorkspaceHost(documentNode), contentHost);
});

test('returns null when no supported editor host exists', () => {
	assert.equal(
		findInlineWorkspaceHost({
			querySelector() {
				return null;
			},
		}),
		null
	);
	assert.equal(findInlineWorkspaceHost(null), null);
});

test('host activation restores the previous class state', () => {
	const classes = new Set();
	const host = {
		classList: {
			add(value) {
				classes.add(value);
			},
			contains(value) {
				return classes.has(value);
			},
			remove(value) {
				classes.delete(value);
			},
		},
	};

	const deactivate = activateInlineWorkspaceHost(host);

	assert.equal(classes.has('ume-inline-workspace-host'), true);
	deactivate();
	assert.equal(classes.has('ume-inline-workspace-host'), false);
});
