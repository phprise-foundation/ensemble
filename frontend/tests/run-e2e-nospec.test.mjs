// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { createServer } from 'node:net';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const runE2E = join(currentDirectory, '..', 'run-e2e.sh');
const harnessConfig = 'specPattern=cypress/harness/passing.cy.js,screenshotOnRunFailure=false';

const freePort = () => new Promise((resolve, reject) => {
  const server = createServer();
  server.once('error', reject);
  server.listen(0, '127.0.0.1', () => {
    const { port } = server.address();
    server.close(() => resolve(port));
  });
});

const run = (args, environment = {}) => execSync(`bash '${runE2E}' ${args}`, {
  encoding: 'utf8',
  stdio: 'pipe',
  timeout: 300000,
  cwd: currentDirectory,
  env: { ...process.env, ...environment },
});

async function ephemeralRun(context) {
  const directory = mkdtempSync(join(tmpdir(), 'e2e-nospec-'));
  context.after(() => rmSync(directory, { recursive: true, force: true }));
  const dataFile = join(directory, 'db.json');
  const port = await freePort();
  const environment = { E2E_PORT: `${port}`, E2E_DATA_FILE: dataFile };
  return { dataFile, environment };
}

test('run-e2e.sh com --spec que não casa, deve retornar status 1', async (context) => {
  const { environment } = await ephemeralRun(context);
  assert.throws(
    () => run(`--config='${harnessConfig}' --spec=invalid-pattern`, environment),
    (error) => error.status === 1,
  );
});

test('run-e2e.sh com --spec="" (string vazia), deve retornar status 0', async (context) => {
  const { environment } = await ephemeralRun(context);
  run(`--config='${harnessConfig}' --spec=`, environment);
});

test('run-e2e.sh sem --spec, deve retornar status 0', async (context) => {
  const { environment } = await ephemeralRun(context);
  run(`--config='${harnessConfig}'`, environment);
});

test('run-e2e.sh entrega o arquivo efêmero pronto depois da rodada', async (context) => {
  const { dataFile, environment } = await ephemeralRun(context);

  run(`--config='${harnessConfig}'`, environment);

  const stored = readFileSync(dataFile, 'utf8');
  assert.doesNotThrow(() => JSON.parse(stored), `o arquivo efêmero precisa sobreviver ao fim da rodada; conteúdo:\n${stored}`);
});
