// @ts-check
// Skeleton RED do refinamento KAN-125: o segredo do isolamento é o dev
// server inteiro obedecer ao E2E_DATA_FILE, não só o backup/restore do
// script. Cenário: "O dev server lê e grava o arquivo efêmero" em
// run-e2e-isolation.feature. A gravação aterrissando no .data/db.json do
// desenvolvedor (ou no efêmero que nunca nasce) mantém este teste vermelho.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { connect, createServer } from 'node:net';
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

// O guarda de fallback da suíte em paralelo rotaciona o .data/db.json do
// desenvolvedor (backup → seed legacy → restore) por toda a duração da sua
// rodada: uma diferença de bytes pode ser só essa rotação legítima em
// andamento. O que nenhum contrato pode fazer é gravar ou truncar o arquivo.
async function assertDeveloperDatabaseUntouched(before) {
  const after = await currentBytes(developerDataFile);
  if (after === before) {
    return;
  }
  const database = JSON.parse(after);
  assert.ok(
    !JSON.stringify(database).includes('Registro do contrato KAN-125'),
    'o db do desenvolvedor não pode receber a gravação do spec efêmero',
  );
}

test('[RED] gravação do spec persiste no arquivo efêmero, nunca no db do desenvolvedor', async (t) => {
  const directory = await mkdtemp(join(tmpdir(), 'e2e-data-'));
  const ephemeral = join(directory, 'db.json');
  const port = await freePort();
  const developerBefore = await currentBytes(developerDataFile);

  const run = spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', writeSpec], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), E2E_DATA_FILE: ephemeral },
    encoding: 'utf8',
    timeout: 300000,
  });
  t.after(() => rm(directory, { recursive: true, force: true }));

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

  await assertDeveloperDatabaseUntouched(developerBefore);
});
