// @ts-check
import { test } from 'node:test';
import { execSync } from 'node:child_process';
import { strict as assert } from 'node:assert';
import { createServer } from 'node:http';
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

  assert.throws(
    () => run({ E2E_PORT: port }),
    (error) => error.status === 1 && /already in use/.test(error.stderr),
  );
});
