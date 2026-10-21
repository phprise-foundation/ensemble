// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { createServer } from 'node:http';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const runE2E = join(currentDirectory, '..', 'run-e2e.sh');

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
  const directory = mkdtempSync(join(tmpdir(), 'e2e-port-'));
  context.after(() => rmSync(directory, { recursive: true, force: true }));
  const dataFile = join(directory, 'db.json');

  assert.throws(
    () => run({ E2E_PORT: port, E2E_DATA_FILE: dataFile }),
    (error) => error.status === 1 && /already in use/.test(error.stderr),
  );

  assert.doesNotThrow(
    () => JSON.parse(readFileSync(dataFile, 'utf8')),
    'o arquivo efêmero semeado antes do aborto precisa continuar legível',
  );
});
