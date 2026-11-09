const assert = require('node:assert/strict');
const {
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const releaseComposer = import('../../scripts/write-release-composer.mjs');

test('release composer metadata keeps the production dependency contract only', async () => {
	const { buildReleaseComposerManifest } = await releaseComposer;
	const source = {
		name: 'example/plugin',
		description: 'Example plugin',
		type: 'wordpress-plugin',
		license: 'GPL-2.0-or-later',
		require: {
			php: '>=8.1',
			'example/runtime': '1.2.3',
			'example/range': '^3.4',
		},
		'require-dev': {
			'example/tests': '9.9.9',
		},
		config: {
			'allow-plugins': false,
		},
		scripts: {
			test: 'phpunit',
		},
	};

	assert.deepEqual(buildReleaseComposerManifest(source), {
		name: 'example/plugin',
		description: 'Example plugin',
		type: 'wordpress-plugin',
		license: 'GPL-2.0-or-later',
		require: {
			php: '>=8.1',
			'example/runtime': '^1.2.3',
			'example/range': '^3.4',
		},
	});
});

test('release composer metadata writer replaces development metadata on disk', async () => {
	const { writeReleaseComposerManifest } = await releaseComposer;
	const root = mkdtempSync(path.join(os.tmpdir(), 'ume-release-composer-'));
	const sourcePath = path.join(root, 'source-composer.json');
	const outputPath = path.join(root, 'release', 'composer.json');

	writeFileSync(
		sourcePath,
		JSON.stringify({
			name: 'example/plugin',
			description: 'Example plugin',
			type: 'wordpress-plugin',
			license: 'GPL-2.0-or-later',
			require: {
				php: '>=8.1',
				'example/runtime': '1.2.3',
			},
			'require-dev': { 'example/tests': '9.9.9' },
			scripts: { test: 'phpunit' },
		}),
	);

	try {
		writeReleaseComposerManifest(sourcePath, outputPath);
		const manifest = JSON.parse(readFileSync(outputPath, 'utf8'));

		assert.deepEqual(manifest, {
			name: 'example/plugin',
			description: 'Example plugin',
			type: 'wordpress-plugin',
			license: 'GPL-2.0-or-later',
			require: {
				php: '>=8.1',
				'example/runtime': '^1.2.3',
			},
		});
		assert.equal('require-dev' in manifest, false);
		assert.equal('scripts' in manifest, false);
	} finally {
		rmSync(root, { recursive: true, force: true });
	}
});

test('release composer metadata rejects incomplete production identity', async () => {
	const { buildReleaseComposerManifest } = await releaseComposer;
	assert.throws(
		() => buildReleaseComposerManifest({
			name: 'example/plugin',
			type: 'wordpress-plugin',
			license: 'GPL-2.0-or-later',
			require: { php: '>=8.1' },
		}),
		/release composer metadata is missing description/i,
	);
});

test('release composer metadata rejects non-string runtime constraints', async () => {
	const { buildReleaseComposerManifest } = await releaseComposer;
	assert.throws(
		() => buildReleaseComposerManifest({
			name: 'example/plugin',
			description: 'Example plugin',
			type: 'wordpress-plugin',
			license: 'GPL-2.0-or-later',
			require: {
				php: '>=8.1',
				'example/runtime': null,
			},
		}),
		/release composer metadata example\/runtime must be a non-empty string/i,
	);
});
