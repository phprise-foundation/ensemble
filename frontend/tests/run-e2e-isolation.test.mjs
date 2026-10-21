// @ts-check
// Skeleton RED do refinamento KAN-125: isolamento de dados por invocação.
// Cenários: run-e2e-isolation.feature. Falham antes da implementação de
// E2E_DATA_FILE (o script ignora a env e o plugin grava em .data/db.json fixo).
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

function runHarness(port, extraEnv = {}) {
  return spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), ...extraEnv },
    encoding: 'utf8',
    timeout: 300000,
  });
}

async function temporaryDataFile() {
  const directory = await mkdtemp(join(tmpdir(), 'e2e-harness-'));
  return join(directory, 'db.json');
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
    'o db do desenvolvedor não pode receber a gravação de um contrato',
  );
}

test('[RED] E2E_DATA_FILE isola o db do desenvolvedor', async (t) => {
  const ephemeral = await temporaryDataFile();
  const port = await freePort();
  const developerBefore = await currentBytes(developerDataFile);

  const run = runHarness(port, { E2E_DATA_FILE: ephemeral });
  t.after(async () => {
    await rm(join(ephemeral, '..'), { recursive: true, force: true });
  });

  assert.equal(run.status, 0, `harness precisa passar; saída:\n${run.stdout}\n${run.stderr}`);

  const stored = await readFile(ephemeral, 'utf8');
  const database = JSON.parse(stored);
  assert.ok(Array.isArray(database.user) === false && !('user' in database), 'o seed efêmero precisa nascer legacy (sem `user`)');

  await assertDeveloperDatabaseUntouched(developerBefore);
});

test('[RED] duas invocações concorrentes com dados e portas próprios passam juntas', async (t) => {
  const [first, second] = await Promise.all([temporaryDataFile(), temporaryDataFile()]);
  const [portA, portB] = await Promise.all([freePort(), freePort()]);
  const developerBefore = await currentBytes(developerDataFile);

  const [runA, runB] = await Promise.all([
    runHarness(portA, { E2E_DATA_FILE: first }),
    runHarness(portB, { E2E_DATA_FILE: second }),
  ]);
  t.after(async () => {
    for (const path of [first, second]) {
      await rm(join(path, '..'), { recursive: true, force: true });
    }
  });

  assert.equal(runA.status, 0, `1ª invocação falhou:\n${runA.stdout}\n${runA.stderr}`);
  assert.equal(runB.status, 0, `2ª invocação falhou:\n${runB.stdout}\n${runB.stderr}`);

  for (const path of [first, second]) {
    const database = JSON.parse(await readFile(path, 'utf8'));
    assert.ok(database && typeof database === 'object', `db efêmero truncado/corrompido: ${path}`);
  }
  await assertDeveloperDatabaseUntouched(developerBefore);
});
