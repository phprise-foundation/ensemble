// @ts-check
// Contrato de isolamento de dados por invocação (KAN-125).
// Cenários: run-e2e-isolation.feature. Falhavam antes de o script/plugin
// obedecerem a E2E_DATA_FILE (o plugin gravava em .data/db.json fixo).
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { strict as assert } from 'node:assert';
import { mkdtemp, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const runE2E = join(frontendRoot, 'run-e2e.sh');
const developerDataFile = join(frontendRoot, '.data', 'db.json');

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

function currentBytes(path) {
  try {
    return readFileSync(path, 'utf8');
  } catch {
    return null;
  }
}

async function temporaryDataFile() {
  const directory = await mkdtemp(join(tmpdir(), 'e2e-harness-'));
  return { directory, dataFile: join(directory, 'db.json') };
}

async function runHarnessOverFile(directory, dataFile) {
  const port = await freePort();
  const run = spawnSync('bash', [runE2E, '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), E2E_DATA_FILE: dataFile },
    encoding: 'utf8',
    timeout: 300000,
  });
  return { ...run, data: currentBytes(dataFile), directory };
}

test('[RED] E2E_DATA_FILE isola o db do desenvolvedor', async (t) => {
  const ephemeral = await temporaryDataFile();
  t.after(async () => {
    await rm(ephemeral.directory, { recursive: true, force: true });
  });
  const developerBefore = currentBytes(developerDataFile);

  const run = await runHarnessOverFile(ephemeral.directory, ephemeral.dataFile);

  assert.equal(run.status, 0, `harness precisa passar; saída:\n${run.stdout}\n${run.stderr}`);
  assert.ok(run.data, 'o arquivo efêmero precisa chegar legível ao fim da rodada');
  const database = JSON.parse(run.data);
  assert.ok(database && typeof database === 'object', `o seed efêmero precisa chegar legível ao fim da rodada:\n${run.data}`);

  if (developerBefore !== null) {
    assert.equal(currentBytes(developerDataFile), developerBefore, 'o db do desenvolvedor precisa permanecer intocado (mesmos bytes)');
  }
});

test('[RED] duas invocações concorrentes com dados e portas próprios passam juntas', async (t) => {
  const first = await temporaryDataFile();
  const second = await temporaryDataFile();
  t.after(async () => {
    await rm(first.directory, { recursive: true, force: true });
    await rm(second.directory, { recursive: true, force: true });
  });
  const developerBefore = currentBytes(developerDataFile);

  const [runA, runB] = await Promise.all([
    runHarnessOverFile(first.directory, first.dataFile),
    runHarnessOverFile(second.directory, second.dataFile),
  ]);

  assert.equal(runA.status, 0, `1ª invocação falhou:\n${runA.stdout}\n${runA.stderr}`);
  assert.equal(runB.status, 0, `2ª invocação falhou:\n${runB.stdout}\n${runB.stderr}`);

  for (const run of [runA, runB]) {
    assert.ok(run.data, 'os dois arquivos efêmeros precisam existir depois da corrida');
    assert.ok(JSON.parse(run.data) && typeof run.data === 'string', 'db efêmero truncado/corrompido');
  }
  if (developerBefore !== null) {
    assert.equal(currentBytes(developerDataFile), developerBefore, 'a corrida não pode atingir o db do desenvolvedor');
  }
});
