// @ts-check
// Contrato de isolamento (KAN-125): o segredo é o dev server inteiro obedecer
// ao E2E_DATA_FILE, não só o backup/restore do script. Cenário: "O dev server
// lê e grava o arquivo efêmero" em run-e2e-isolation.feature. A gravação
// aterrissando no .data/db.json do desenvolvedor (ou no efêmero que nunca
// nasce) torna este teste vermelho.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { strict as assert } from 'node:assert';
import { readFile, mkdtemp, rm } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const runE2E = join(frontendRoot, 'run-e2e.sh');
const developerDataFile = join(frontendRoot, '.data', 'db.json');

const writeSpec = 'cypress/harness/writes.cy.js';
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

test('[RED] gravação do spec persiste no arquivo efêmero, nunca no db do desenvolvedor', async (t) => {
  const directory = await mkdtemp(join(tmpdir(), 'e2e-data-'));
  t.after(() => rm(directory, { recursive: true, force: true }));
  const ephemeral = join(directory, 'db.json');
  const port = await freePort();
  const developerBefore = await currentBytes(developerDataFile);
  const run = spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', writeSpec], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), E2E_DATA_FILE: ephemeral },
    encoding: 'utf8',
    timeout: 300000,
  });

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `harness precisa passar com o spec de escrita; saída:\n${output}`);

  const ephemeralAfter = await currentBytes(ephemeral);
  assert.ok(ephemeralAfter, 'o arquivo efêmero precisa existir depois da rodada');
  const database = JSON.parse(ephemeralAfter);
  assert.equal(
    database.user?.displayName,
    'Registro do contrato KAN-125',
    `a gravação do spec precisa persistir no arquivo efêmero; conteúdo:\n${ephemeralAfter}`,
  );

  const developerAfter = await currentBytes(developerDataFile);
  // O modo fallback restaura o arquivo da árvore: o que se prova aqui é que a
  // gravação do spec nunca aterrissa nele, com ou sem outra rodada em curso.
  const developer = JSON.parse(developerAfter ?? '{}');
  assert.notEqual(developer.user?.displayName, 'Registro do contrato KAN-125');
  if (developerBefore !== null) {
    assert.equal(developerAfter, developerBefore, 'o db do desenvolvedor precisa permanecer intocado (mesmos bytes)');
  }
});
