const assert = require('node:assert/strict');
const test = require('node:test');

const { createPreviewSync } = require('../../src/Editor/preview-sync.js');

test('preview scheduling coalesces burst input and runs only the latest source', () => {
	const timers = new Map();
	let nextTimerId = 0;
	const sync = createPreviewSync({
		delayMs: 150,
		schedule: (callback, delay) => {
			const id = ++nextTimerId;
			timers.set(id, { callback, delay });
			return id;
		},
		cancelSchedule: (id) => timers.delete(id),
	});
	const rendered = [];

	sync.schedule('a', (value) => rendered.push(value));
	sync.schedule('ab', (value) => rendered.push(value));
	timers.get(2).callback();

	assert.deepEqual(rendered, ['ab']);
});
