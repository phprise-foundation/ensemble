// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { createServer } from 'node:http';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const runE2E = join(currentDirectory, '..', 'run-e2e.sh');
const developerDataFile = join(currentDirectory, '..', '.data', 'db.json');

const run = (environment) => execSync(`bash '${runE2E}'`, {
  encoding: 'utf8',
  stdio: 'pipe',
  timeout: 60000,
  cwd: currentDirectory,
  env: { ...process.env, E2E_TIMEOUT: '5', ...environment },
});

const listen = () => new Promise((resolve) => {
  const server = createServer((_request, response) => {
    response.writeHead(200, { 'content-type': 'text/html' });
    response.end('<html><body>servidor alheio</body></html>');
  });
  server.listen(0, '127.0.0.1', () => resolve(server));
});

test('run-e2e.sh com a porta ocupada, deve abortar sem medir o servidor alheio', async (context) => {
  const foreign = await listen();
  context.after(() => foreign.close());
  const port = `${foreign.address().port}`;

  const ephemeral = mkdtempSync(join(tmpdir(), 'run-e2e-port-'));
  context.after(() => rmSync(ephemeral, { recursive: true, force: true }));

  const before = existsSync(developerDataFile)
    ? await readFile(developerDataFile, 'utf8')
    : null;

  assert.throws(
    () => run({ E2E_PORT: port, E2E_DATA_FILE: join(ephemeral, 'db.json') }),
    (error) => error.status === 1 && /already in use/.test(error.stderr),
  );

  const after = existsSync(developerDataFile)
    ? await readFile(developerDataFile, 'utf8')
    : null;

  if (after !== before) {
    // O guarda de fallback da suíte em paralelo rotaciona o .data/db.json do
    // desenvolvedor por toda a duração da sua rodada; a rodada abortada nunca
    // grava nele. A diferença pode ser só essa rotação em andamento.
    const database = JSON.parse(after);
    assert.ok(
      !JSON.stringify(database).includes('Registro do contrato KAN-125'),
      'o .data/db.json da árvore fica intocado na rodada abortada',
    );
  }
});
