// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const runE2E = join(currentDirectory, '..', 'run-e2e.sh');

const run = (args) => execSync(`bash '${runE2E}' ${args}`, {
  encoding: 'utf8',
  stdio: 'pipe',
  timeout: 300000,
  cwd: currentDirectory,
});

test('run-e2e.sh com --spec que não casa, deve retornar status 1', () => {
  assert.throws(() => run('--spec=invalid-pattern'), (error) => error.status === 1);
});

test('run-e2e.sh com --spec="" (string vazia), deve retornar status 0', () => {
  run('--spec=');
});

test('run-e2e.sh sem --spec, deve retornar status 0', () => {
  run('');
});
