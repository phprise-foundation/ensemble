// @ts-check
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { strict as assert } from 'node:assert';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const runE2E = join(frontendRoot, 'run-e2e.sh');
const passingSpec = 'cypress/harness/passing.cy.js';
const harnessConfig = 'specPattern=cypress/harness/passing.cy.js,screenshotOnRunFailure=false';

function freePort() {
  return new Promise((resolve, reject) => {
    const server = createServer();
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
  });
}

// O runner do `node --test` marca os arquivos de teste com NODE_TEST_CONTEXT no
// env do subprocesso. Sem limpar a marca, o `node --test` neto herda o modo
// child, lê o stdin vazio e sai com 0 sem executar um único teste.
const { NODE_TEST_CONTEXT, ...outerEnvironment } = process.env;

function occupyForeignPort() {
  return new Promise((resolve) => {
    const server = createServer((request, response) => {
      response.writeHead(200, { 'content-type': 'text/html' });
      response.end('<html><body>servidor alheio na 5173</body></html>');
    });
    server.once('error', (error) => {
      // A porta já está ocupada por terceiros: o objetivo do teste continua igual.
      resolve({ server: null, code: error.code });
    });
    server.listen(5173, '127.0.0.1', () => resolve({ server, code: null }));
  });
}

function runHarness(target, port) {
  return spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', passingSpec], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), E2E_FALLBACK_TARGET: target },
    encoding: 'utf8',
    timeout: 300000,
  });
}

// O modo fallback restaura o MESMO arquivo que o harness do `run-e2e.sh`
// sementeia; por isso roda sobre uma cópia própria, jamais sobre o db real
// que os outros contratos leem em paralelo.
test('[RED] sem E2E_DATA_FILE o modo fallback restaura os mesmos bytes', async (t) => {
  const directory = mkdtempSync(join(tmpdir(), 'e2e-fallback-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const target = join(directory, 'db.json');
  writeFileSync(target, `${JSON.stringify({ workspaces: [{ id: 'alheio', name: 'marca do modo fallback' }] }, null, 2)}\n`);

  const port = await freePort();
  const before = readFileSync(target, 'utf8');

  const run = runHarness(target, port);

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `o modo fallback precisa passar; saída:\n${output}`);
  assert.equal(readFileSync(target, 'utf8'), before, 'o modo fallback precisa devolver os mesmos bytes');
});

test('[RED] contratos de e2e passam em paralelo com a porta 5173 ocupada', async (t) => {
  const foreign = await occupyForeignPort();
  const portWasFree = foreign.server !== null;
  t.after(() => {
    if (portWasFree && foreign.server) {
      foreign.server.close();
    }
  });

  const run = spawnSync('node', [
    '--test',
    'tests/run-e2e-nospec.test.mjs',
    'tests/run-e2e-exit-code.test.mjs',
  ], {
    cwd: frontendRoot,
    encoding: 'utf8',
    timeout: 600000,
    env: outerEnvironment,
  });

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `os dois arquivos de contrato precisam passar juntos; saída:\n${output}`);
  assert.doesNotMatch(output, /Unexpected end of JSON input/, 'nenhum JSON truncado na corrida paralela');
  assert.doesNotMatch(output, /already in use/, 'nenhum contrato pode acordar na porta 5173 ocupada');

});
