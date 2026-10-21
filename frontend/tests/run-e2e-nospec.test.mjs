// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { createServer } from 'node:net';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const runE2E = join(currentDirectory, '..', 'run-e2e.sh');

const freePort = async () => {
  return new Promise((resolve, reject) => {
    const server = createServer();
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
  });
};

const run = (args, environment = {}) => {
  const dataDirectory = mkdtempSync(join(tmpdir(), 'run-e2e-nospec-'));
  const dataFile = join(String(dataDirectory), 'db.json');
  const merged = { ...process.env, ...environment, E2E_DATA_FILE: dataFile };
  try {
    return execSync(`bash '${runE2E}' ${args}`, {
      encoding: 'utf8',
      stdio: 'pipe',
      timeout: 300000,
      cwd: currentDirectory,
      env: merged,
    });
  } finally {
    rmSync(String(dataDirectory), { recursive: true, force: true });
  }
};

const harnessConfig = 'specPattern=cypress/harness/passing.cy.js,screenshotOnRunFailure=false';

test('run-e2e.sh com --spec que não casa, deve retornar status 1', async () => {
  const port = await freePort();
  assert.throws(() => run(`--config='${harnessConfig}' --spec=invalid-pattern`, { E2E_PORT: `${port}` }), (error) => error.status === 1);
});

test('run-e2e.sh com --spec="" (string vazia), deve retornar status 0', async () => {
  const port = await freePort();
  run(`--config='${harnessConfig}' --spec=`, { E2E_PORT: `${port}` });
});

test('run-e2e.sh sem --spec, deve retornar status 0', async () => {
  const port = await freePort();
  run(`--config='${harnessConfig}'`, { E2E_PORT: `${port}` });
});

test('run-e2e.sh com E2E_DATA_FILE não toca o .data do desenvolvedor', async () => {
  const port = await freePort();
  const developerDatabase = join(currentDirectory, '..', '.data', 'db.json');
  const existed = existsSync(developerDatabase);
  run(`--config='${harnessConfig}'`, { E2E_PORT: `${port}` });
  assert.equal(existsSync(developerDatabase), existed);
});
