// @ts-check
// Guarda de compatibilidade do refinamento KAN-125 (cenário
// "Sem E2E_DATA_FILE o comportamento atual é preservado" em
// run-e2e-isolation.feature). Este teste passa ANTES do fix: o
// run-e2e.sh atual já faz backup → seed legacy → restauração de
// .data/db.json quando a env não vem. O Coder refatora o script sem
// perder essa garantia.
// Rodado sozinho é determinístico; na suíte toda, antes da migração dos
// demais contratos, a própria corrida do KAN-125 pode derrubá-lo.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { connect, createServer } from 'node:net';
import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const runE2E = join(frontendRoot, 'run-e2e.sh');
const developerDataFile = join(frontendRoot, '.data', 'db.json');

const harnessConfig = 'specPattern=cypress/harness/*.cy.js,screenshotOnRunFailure=false';

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

async function currentBytes(path) {
  try {
    return await readFile(path, 'utf8');
  } catch {
    return null;
  }
}

test('[GUARDA] sem E2E_DATA_FILE, o db do desenvolvedor é restaurado com os mesmos bytes', async () => {
  const port = await freePort();
  const developerBefore = await currentBytes(developerDataFile);

  const run = spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port) },
    encoding: 'utf8',
    timeout: 300000,
  });

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `harness precisa passar; saída:\n${output}`);

  const developerAfter = await currentBytes(developerDataFile);
  assert.equal(developerAfter, developerBefore, 'o db do desenvolvedor precisa voltar com os mesmos bytes da rodada');
});
