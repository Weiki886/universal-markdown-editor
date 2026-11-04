#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { readFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const allowedLicenses = new Set([
  '0BSD',
  'Apache-2.0',
  'Apache-2.0 WITH LLVM-exception',
  'BSD',
  'BSD-2-Clause',
  'BSD-3-Clause',
  'BlueOak-1.0.0',
  'CC-BY-3.0',
  'CC-BY-4.0',
  'CC0-1.0',
  'FSL-1.1-Apache-2.0',
  'GPL-2.0-only',
  'GPL-2.0-or-later',
  'GPL-3.0-only',
  'GPL-3.0-or-later',
  'ISC',
  'MIT',
  'MIT-0',
  'MPL-2.0',
  'Public Domain',
  'Python-2.0',
  'Unlicense',
  'WTFPL',
  'Zlib',
]);

const licenseEvidence = new Map([
  [
    '@php-wasm/util@3.1.62',
    {
      license: 'GPL-2.0-only',
      file: 'node_modules/@php-wasm/util/LICENSE',
      sha256: '8177f97513213526df2cf6184d8ff986c675afb514d4e68a404010521b880643',
    },
  ],
  [
    '@wp-playground/blueprints@3.1.62',
    {
      license: 'GPL-2.0-only',
      file: 'node_modules/@wp-playground/blueprints/LICENSE',
      sha256: '8177f97513213526df2cf6184d8ff986c675afb514d4e68a404010521b880643',
    },
  ],
  [
    'svg-tags@1.0.0',
    {
      license: 'MIT',
      file: 'node_modules/svg-tags/LICENSE',
      sha256: '4fa26a349d96c6bf268296011d84a70d18a1e85dfbef9a3c3f785c97e28d1f5d',
    },
  ],
]);

const failures = [];

function isAllowedExpression(expression) {
  if (typeof expression !== 'string' || expression.trim() === '') {
    return false;
  }

  const normalized = expression
    .replaceAll('(', '')
    .replaceAll(')', '')
    .replace(/\s+/g, ' ')
    .trim();

  if (allowedLicenses.has(normalized)) {
    return true;
  }

  return normalized
    .split(/\s+(?:AND|OR)\s+/)
    .map((part) => part.trim())
    .filter(Boolean)
    .every((part) => allowedLicenses.has(part));
}

function checkComposerGroup(label, packages) {
  for (const pkg of packages) {
    const licenses = Array.isArray(pkg.license) ? pkg.license : [pkg.license];
    if (licenses.length === 0 || !licenses.some(isAllowedExpression)) {
      failures.push(
        `Composer ${label} package ${pkg.name}@${pkg.version} has no approved license: ${JSON.stringify(pkg.license)}`,
      );
    }
  }
}

function checkComposer() {
  const lock = JSON.parse(readFileSync(path.join(root, 'composer.lock'), 'utf8'));
  const production = lock.packages ?? [];
  const development = lock['packages-dev'] ?? [];

  checkComposerGroup('production', production);
  checkComposerGroup('development', development);

  return {
    production: production.length,
    development: development.length,
  };
}

function checkNpm() {
  const lock = JSON.parse(readFileSync(path.join(root, 'package-lock.json'), 'utf8'));
  const packages = Object.entries(lock.packages ?? {}).filter(([packagePath]) => packagePath !== '');

  for (const [packagePath, pkg] of packages) {
    let license = pkg.license;

    if (!license) {
      const match = packagePath.match(/^node_modules\/(.+)$/);
      const packageName = match?.[1];
      const evidence = packageName
        ? licenseEvidence.get(`${packageName}@${pkg.version}`)
        : null;

      if (!evidence) {
        failures.push(
          `npm package ${packagePath}@${pkg.version} has no license metadata or reviewed evidence.`,
        );
        continue;
      }

      const evidencePath = path.join(root, evidence.file);
      if (!existsSync(evidencePath)) {
        failures.push(`npm package ${packagePath}@${pkg.version} license evidence is missing.`);
        continue;
      }

      const digest = createHash('sha256').update(readFileSync(evidencePath)).digest('hex');
      if (digest !== evidence.sha256) {
        failures.push(
          `npm package ${packagePath}@${pkg.version} license evidence hash changed: ${digest}`,
        );
        continue;
      }

      license = evidence.license;
    }

    if (!isAllowedExpression(license)) {
      failures.push(
        `npm package ${packagePath}@${pkg.version} has no approved license: ${JSON.stringify(license)}`,
      );
    }
  }

  return packages.length;
}

if (process.argv.includes('--self-test')) {
  const probes = [
    ['MIT', true],
    ['MIT OR Apache-2.0', true],
    ['(MIT AND BSD-3-Clause)', true],
    ['GPL-2.0-only', true],
    ['UNLICENSED', false],
    ['', false],
  ];

  for (const [expression, expected] of probes) {
    const actual = isAllowedExpression(expression);
    if (actual !== expected) {
      throw new Error(`License expression probe failed for ${JSON.stringify(expression)}.`);
    }
  }
}

const composerCounts = checkComposer();
const npmCount = checkNpm();

if (failures.length > 0) {
  for (const failure of failures) {
    console.error(`ERROR: ${failure}`);
  }
  process.exit(1);
}

console.log(`Composer production packages checked: ${composerCounts.production}`);
console.log(`Composer development packages checked: ${composerCounts.development}`);
console.log(`npm locked packages checked: ${npmCount}`);
console.log(`Reviewed missing-metadata packages: ${licenseEvidence.size}`);
console.log('License check passed.');
