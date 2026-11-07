const assert = require('node:assert/strict');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const {
	mkdtempSync,
	readFileSync,
	writeFileSync,
	mkdirSync,
	rmSync,
} = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const packageArtifact = import('../../scripts/package-artifact.mjs');

test('creates a versioned development ZIP with source SHA and matching checksum', async () => {
	const { createArtifact } = await packageArtifact;
	const root = mkdtempSync(path.join(os.tmpdir(), 'ume-artifact-'));
	const sourceDir = path.join(root, 'plugin');
	const outputDir = path.join(root, 'artifacts');
	const sourceSha = 'a'.repeat(40);

	mkdirSync(sourceDir);
	writeFileSync(
		path.join(sourceDir, 'universal-markdown-editor.php'),
		'<?php\n * Version: 0.1.0\n',
	);
	writeFileSync(path.join(sourceDir, 'plugin.php'), '<?php // package fixture');
	writeFileSync(path.join(sourceDir, '.DS_Store'), 'must not ship');

	try {
		const artifact = createArtifact({ sourceDir, outputDir, sourceSha });
		const zip = readFileSync(artifact.zipPath);
		const expectedName = `universal-markdown-editor-0.1.0-${sourceSha.slice(0, 12)}.zip`;
		const entries = execFileSync('unzip', ['-Z1', artifact.zipPath], {
			encoding: 'utf8',
		});

		assert.equal(path.basename(artifact.zipPath), expectedName);
		assert.match(entries, /universal-markdown-editor\/universal-markdown-editor\.php/);
		assert.match(entries, /universal-markdown-editor\/BUILD-INFO\.txt/);
		assert.doesNotMatch(entries, /\.DS_Store/);
		assert.match(
			execFileSync('unzip', ['-p', artifact.zipPath, 'universal-markdown-editor/BUILD-INFO.txt'], {
				encoding: 'utf8',
			}),
			new RegExp(`Source commit: ${sourceSha}`),
		);
		assert.equal(
			readFileSync(artifact.checksumPath, 'utf8'),
			`${createHash('sha256').update(zip).digest('hex')}  ${expectedName}\n`,
		);
		assert.equal(readFileSync(artifact.sourcePath, 'utf8'), `${sourceSha}\n`);
	} finally {
		rmSync(root, { recursive: true, force: true });
	}
});

test('rejects a non-full source commit SHA before writing artifacts', async () => {
	const { createArtifact } = await packageArtifact;
	const root = mkdtempSync(path.join(os.tmpdir(), 'ume-artifact-invalid-'));
	const sourceDir = path.join(root, 'plugin');

	mkdirSync(sourceDir);
	writeFileSync(path.join(sourceDir, 'universal-markdown-editor.php'), '* Version: 0.1.0');

	try {
		assert.throws(
			() => createArtifact({ sourceDir, outputDir: path.join(root, 'out'), sourceSha: 'abc123' }),
			/source commit SHA must be 40 hexadecimal characters/i,
		);
	} finally {
		rmSync(root, { recursive: true, force: true });
	}
});

test('rejects a package directory without the plugin entry file', async () => {
	const { createArtifact } = await packageArtifact;
	const root = mkdtempSync(path.join(os.tmpdir(), 'ume-artifact-missing-'));

	try {
		assert.throws(
			() => createArtifact({
				sourceDir: root,
				outputDir: path.join(root, 'out'),
				sourceSha: 'b'.repeat(40),
			}),
			/plugin entry file is missing/i,
		);
	} finally {
		rmSync(root, { recursive: true, force: true });
	}
});
