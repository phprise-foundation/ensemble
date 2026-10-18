// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(__dirname, '..');
const script = resolve(frontendDir, 'run-e2e.sh');

test('run-e2e.sh com --spec que não casa, deve retornar status 1', () => {
  try {
    execSync(`bash "${script}" --spec=invalid-pattern`, {
      encoding: 'utf8',
      stdio: 'pipe',
      timeout: 300000,
      cwd: frontendDir
    });
    assert.fail('O comando deveria ter falhado com exit code 1');
  } catch (error) {
    assert.equal(error.status, 1, 'O comando deve retornar exit code 1');
  }
});

test('run-e2e.sh com --spec="" (string vazia), deve retornar status 1', () => {
  try {
    execSync(`bash "${script}" --spec=""`, {
      encoding: 'utf8',
      stdio: 'pipe',
      timeout: 300000,
      cwd: frontendDir
    });
    assert.fail('O comando deveria ter falhado com exit code 1');
  } catch (error) {
    assert.equal(error.status, 1, 'O comando deve retornar exit code 1');
  }
});

test('run-e2e.sh sem --spec, deve retornar status 0', () => {
  try {
    execSync(`bash "${script}"`, {
      encoding: 'utf8',
      stdio: 'pipe',
      timeout: 300000,
      cwd: frontendDir
    });
  } catch (error) {
    assert.fail('O comando deveria ter sucesso com exit code 0');
  }
});
