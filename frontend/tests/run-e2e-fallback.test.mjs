// @ts-check
// Guarda de compatibilidade do refinamento KAN-125 (cenário
// "Sem E2E_DATA_FILE o comportamento atual é preservado" em
// run-e2e-isolation.feature). O modo fallback roda sobre o `.data/db.json`
// da árvore, que outros contratos leem em paralelo: este teste nunca usa o
// db real, e sim uma cópia descartável preparada por ele mesmo.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { strict as assert } from 'node:assert';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const runE2E = join(frontendRoot, 'run-e2e.sh');

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

test('[GUARDA] sem E2E_DATA_FILE, o db do modo fallback é restaurado com os mesmos bytes', async (t) => {
  const directory = mkdtempSync(join(tmpdir(), 'e2e-fallback-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  const target = join(directory, 'db.json');
  writeFileSync(target, `${JSON.stringify({ workspaces: [{ id: 'alheio', name: 'marca do modo fallback' }] }, null, 2)}\n`);
  const port = await freePort();
  const fallbackBefore = readFileSync(target, 'utf8');

  const run = spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), E2E_FALLBACK_TARGET: target },
    encoding: 'utf8',
    timeout: 300000,
  });

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `harness precisa passar; saída:\n${output}`);

  const fallbackAfter = readFileSync(target, 'utf8');
  assert.equal(fallbackAfter, fallbackBefore, 'o db do modo fallback precisa voltar com os mesmos bytes da rodada');
});
