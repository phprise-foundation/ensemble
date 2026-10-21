// @ts-check
// Skeleton RED do refinamento KAN-125: critério de aceite literal.
// Cenários: run-e2e-parallel.feature. Hoje falha de dois jeitos ao mesmo
// tempo: `run-e2e-nospec` acorda na porta 5173 ocupada (não escolhe porta
// própria) e ambos os contratos escrevem sobre o mesmo `.data/db.json`
// enquanto `node --test` os roda em paralelo. O teste só fica verde quando
// os dois arquivos de contrato usarem E2E_PORT e E2E_DATA_FILE próprios.
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { strict as assert } from 'node:assert';

const here = dirname(fileURLToPath(import.meta.url));
const frontendRoot = join(here, '..');
const foreignPort = 5173;
const developerDataFile = join(frontendRoot, '.data', 'db.json');

async function currentBytes(path) {
  try {
    return await readFile(path, 'utf8');
  } catch {
    return null;
  }
}

// O runner do `node --test` marca os arquivos de teste com NODE_TEST_CONTEXT no
// env do subprocesso. Sem limpar a marca, o `node --test` neto herda o modo
// child, lê o stdin vazio e sai com 0 sem executar um único teste.
const { NODE_TEST_CONTEXT, ...outerEnvironment } = process.env;

function occupyForeignPort() {
  return new Promise((resolve) => {
    const server = createServer((request, response) => {
      response.writeHead(200, { 'content-type': 'text/html' });
      response.end('<html><body>servidor alheio na 5173</body></html>');
    });
    server.once('error', (error) => {
      // A porta já está ocupada por terceiros: o objetivo do teste continua igual.
      resolve({ server: null, code: error.code });
    });
    server.listen(foreignPort, '127.0.0.1', () => resolve({ server, code: null }));
  });
}

test('[RED] contratos de e2e passam em paralelo com a porta 5173 ocupada', async (t) => {
  const foreign = await occupyForeignPort();
  const portWasFree = foreign.server !== null;
  t.after(() => {
    if (portWasFree && foreign.server) {
      foreign.server.close();
    }
  });

  const developerBefore = await currentBytes(developerDataFile);

  const run = spawnSync('node', [
    '--test',
    'tests/run-e2e-nospec.test.mjs',
    'tests/run-e2e-exit-code.test.mjs',
  ], {
    cwd: frontendRoot,
    encoding: 'utf8',
    timeout: 600000,
    env: outerEnvironment,
  });

  const output = `${run.stdout || ''}\n${run.stderr || ''}`;
  assert.equal(run.status, 0, `os dois arquivos de contrato precisam passar juntos; saída:\n${output}`);
  assert.doesNotMatch(output, /Unexpected end of JSON input/, 'nenhum JSON truncado na corrida paralela');
  assert.doesNotMatch(output, /already in use/, 'nenhum contrato pode acordar na porta 5173 ocupada');

  const developerAfter = await currentBytes(developerDataFile);
  assert.equal(developerAfter, developerBefore, 'a corrida paralela não pode tocar o .data/db.json da árvore');
});
