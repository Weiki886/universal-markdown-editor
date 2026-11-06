import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
	cpSync,
	mkdtempSync,
	mkdirSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const PLUGIN_SLUG = 'universal-markdown-editor';
const ENTRY_FILE = `${PLUGIN_SLUG}.php`;

export function createArtifact({ sourceDir, outputDir, sourceSha }) {
	if (typeof sourceSha !== 'string' || !/^[a-f0-9]{40}$/i.test(sourceSha)) {
		throw new Error('The source commit SHA must be 40 hexadecimal characters.');
	}

	const resolvedSource = path.resolve(sourceDir);
	const entryPath = path.join(resolvedSource, ENTRY_FILE);
	let entry;

	try {
		entry = readFileSync(entryPath, 'utf8');
	} catch {
		throw new Error(`The plugin entry file is missing: ${entryPath}`);
	}

	const version = entry.match(/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m)?.[1];
	if (!version) {
		throw new Error('The plugin entry file does not declare a valid semantic version.');
	}

	const resolvedOutput = path.resolve(outputDir);
	mkdirSync(resolvedOutput, { recursive: true });
	const artifactBase = `${PLUGIN_SLUG}-${version}-${sourceSha.slice(0, 12)}`;
	const zipPath = path.join(resolvedOutput, `${artifactBase}.zip`);
	const checksumPath = `${zipPath}.sha256`;
	const sourcePath = `${zipPath}.source`;
	const stagingRoot = mkdtempSync(path.join(os.tmpdir(), `${PLUGIN_SLUG}-artifact-`));
	const stagedPlugin = path.join(stagingRoot, PLUGIN_SLUG);

	try {
		cpSync(resolvedSource, stagedPlugin, {
			recursive: true,
			filter: (source) => path.basename(source) !== '.DS_Store',
		});

		const buildInfo = [
			`Plugin: ${PLUGIN_SLUG}`,
			`Version: ${version}`,
			`Source commit: ${sourceSha.toLowerCase()}`,
			'Artifact type: development build; not an official WordPress.org release.',
			'',
		].join('\n');
		writeFileSync(path.join(stagedPlugin, 'BUILD-INFO.txt'), buildInfo, { flag: 'wx' });

		execFileSync('zip', ['-q', '-r', zipPath, PLUGIN_SLUG], {
			cwd: stagingRoot,
		});

		const digest = createHash('sha256').update(readFileSync(zipPath)).digest('hex');
		writeFileSync(checksumPath, `${digest}  ${path.basename(zipPath)}\n`, { flag: 'w' });
		writeFileSync(sourcePath, `${sourceSha.toLowerCase()}\n`, { flag: 'w' });

		return { zipPath, checksumPath, sourcePath };
	} catch (error) {
		rmSync(zipPath, { force: true });
		rmSync(checksumPath, { force: true });
		rmSync(sourcePath, { force: true });
		throw error;
	} finally {
		rmSync(stagingRoot, { recursive: true, force: true });
	}
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const root = process.cwd();
	const sourceSha = process.env.UME_SOURCE_SHA
		?? execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim();
	const artifact = createArtifact({
		sourceDir: path.join(root, 'dist', PLUGIN_SLUG),
		outputDir: path.join(root, 'dist', 'artifacts'),
		sourceSha,
	});

	for (const key of ['zipPath', 'checksumPath', 'sourcePath']) {
		console.log(`${key}: ${artifact[key]}`);
	}
}
