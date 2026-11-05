const assert = require('node:assert/strict');
const test = require('node:test');

const { createPreviewRequest } = require('../../src/Editor/preview-request.js');

test('only the newest preview response is applied', async () => {
    const preview = createPreviewRequest();
    const results = [];
    const pending = new Map();
    const send = (source, { signal }) => new Promise((resolve) => {
        pending.set(source, { resolve, signal });
    });
    const onSuccess = (result) => results.push(result);
    const onError = (error) => assert.fail(error);

    const first = preview.run('first', send, onSuccess, onError);
    const second = preview.run('second', send, onSuccess, onError);

    assert.equal(pending.get('first').signal.aborted, true);
    pending.get('second').resolve('new preview');
    await second;
    pending.get('first').resolve('stale preview');
    await first;

    assert.deepEqual(results, ['new preview']);
});

test('cancel prevents a pending preview from changing visible state', async () => {
    const preview = createPreviewRequest();
    const results = [];
    let resolveRequest;
    const request = preview.run(
        'draft',
        () => new Promise((resolve) => { resolveRequest = resolve; }),
        (result) => results.push(result),
        (error) => assert.fail(error)
    );

    preview.cancel();
    resolveRequest('late preview');
    await request;

    assert.deepEqual(results, []);
});

test('a current preview error is reported without replacing successful content', async () => {
    const preview = createPreviewRequest();
    const errors = [];
    const results = [];
    const failure = new Error('preview unavailable');

    await preview.run(
        'draft',
        async () => { throw failure; },
        (result) => results.push(result),
        (error) => errors.push(error)
    );

    assert.deepEqual(results, []);
    assert.deepEqual(errors, [failure]);
});
