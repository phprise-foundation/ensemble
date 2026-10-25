// @ts-check
// Esqueleto nascido verde do refinamento KAN-147: guarda da fronteira
// 1024/1025 do sidebar. Cenários: sidebar-rail.feature (fronteira de
// desktop). Era vermelho antes de d7e76a2 (fix KAN-84, PR #125): o
// isMobile só atualizava pelo evento change da media query, que o
// Electron via cy.viewport não dispara; hoje o resize da janela
// recalcula a flag e o repro do item (comando abaixo) passa.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { strict as assert } from 'node:assert';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');

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

test('a fronteira 1024/1025 permanece verde no comando canonico do E2E', async () => {
  const port = await freePort();
  const run = spawnSync('bash', [join(frontendRoot, 'run-e2e.sh'), '--spec', 'cypress/e2e/sidebar-rail.cy.js'], {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port) },
    encoding: 'utf8',
    timeout: 300000,
  });
  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(
    run.status,
    0,
    `o repro do KAN-147 precisa passar:\n${output.slice(-4000)}`,
  );
});
