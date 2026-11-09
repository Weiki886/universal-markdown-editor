import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const REQUIRED_FIELDS = ['name', 'description', 'type', 'license'];
const DEPENDENCY_FIELD = 'require';
const RELEASE_FIELDS = [...REQUIRED_FIELDS, DEPENDENCY_FIELD];
const EXACT_VERSION = /^v?(\d+\.\d+\.\d+)$/;

function assertObject(value, label) {
	if (value === null || typeof value !== 'object' || Array.isArray(value)) {
		throw new Error(`Release Composer metadata ${label} must be an object.`);
	}
}

function releaseConstraint(packageName, constraint) {
	if (typeof constraint !== 'string' || constraint.trim() === '') {
		throw new Error(`Release Composer metadata ${packageName} must be a non-empty string.`);
	}

	const exactVersion = packageName.includes('/') ? constraint.match(EXACT_VERSION) : null;

	return exactVersion ? `^${exactVersion[1]}` : constraint;
}

export function buildReleaseComposerManifest(source) {
	assertObject(source, 'source');

	for (const field of REQUIRED_FIELDS) {
		if (typeof source[field] !== 'string' || source[field].trim() === '') {
			throw new Error(`Release Composer metadata is missing ${field}.`);
		}
	}

	assertObject(source[DEPENDENCY_FIELD], DEPENDENCY_FIELD);

	const manifest = {};
	for (const field of RELEASE_FIELDS) {
		manifest[field] = source[field];
	}
	manifest[DEPENDENCY_FIELD] = Object.fromEntries(
		Object.entries(source[DEPENDENCY_FIELD]).map(([packageName, constraint]) => [
			packageName,
			releaseConstraint(packageName, constraint),
		]),
	);

	return manifest;
}

export function writeReleaseComposerManifest(sourcePath, outputPath) {
	const source = JSON.parse(readFileSync(sourcePath, 'utf8'));
	const manifest = buildReleaseComposerManifest(source);

	mkdirSync(path.dirname(outputPath), { recursive: true });
	writeFileSync(outputPath, `${JSON.stringify(manifest, null, 2)}\n`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const [sourcePath, outputPath] = process.argv.slice(2);

	if (!sourcePath || !outputPath) {
		console.error('Usage: write-release-composer.mjs <source> <output>');
		process.exit(1);
	}

	writeReleaseComposerManifest(path.resolve(sourcePath), path.resolve(outputPath));
}
