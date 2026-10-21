import { spawnSync } from 'node:child_process'
import { connect, createServer } from 'node:net'
import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const frontendRoot = join(here, '..')
const harnessConfig = 'specPattern=cypress/harness/*.cy.js,screenshotOnRunFailure=false'

function runHarness(command, args, port, extraEnv = {}) {
  return spawnSync(command, args, {
    cwd: frontendRoot,
    env: { ...process.env, E2E_PORT: String(port), ...extraEnv },
    encoding: 'utf8',
    timeout: 240000,
  })
}

function outputOf(run) {
  return `${run.stdout || ''}\n${run.stderr || ''}`
}

function ephemeralDataFile(t) {
  const directory = mkdtempSync(join(tmpdir(), 'e2e-exit-code-'))
  t.after(() => rmSync(directory, { recursive: true, force: true }))
  return join(directory, 'db.json')
}

function freePort() {
  return new Promise((resolve, reject) => {
    const server = createServer()
    server.once('error', reject)
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address()
      server.close(() => resolve(port))
    })
  })
}

function portIsFree(port) {
  return new Promise((resolve) => {
    const socket = connect({ host: '127.0.0.1', port })
    socket.setTimeout(1000)
    socket.once('connect', () => {
      socket.destroy()
      resolve(false)
    })
    socket.once('error', () => resolve(true))
    socket.once('timeout', () => {
      socket.destroy()
      resolve(true)
    })
  })
}

test('spec vermelho reprova o comando canonico e nao deixa o dev server de pe', async (t) => {
  const port = await freePort()
  const run = runHarness(
    'npm',
    ['run', 'test:e2e', '--', '--config', harnessConfig, '--spec', 'cypress/harness/failing.cy.js'],
    port,
    { E2E_DATA_FILE: ephemeralDataFile(t) }
  )
  const output = outputOf(run)
  assert.match(output, /1 of 1 failed/, `o Cypress precisa ter rodado e reprovado de verdade:\n${output}`)
  assert.notEqual(
    run.status,
    0,
    `suite vermelha precisa reprovar o comando; saiu com status ${run.status}:\n${output}`
  )
  assert.equal(await portIsFree(port), true, 'o dev server precisa ser derrubado mesmo no caminho vermelho')
})

test('spec verde mantem o comando verde', async (t) => {
  const port = await freePort()
  const run = runHarness(
    'bash',
    ['run-e2e.sh', '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'],
    port,
    { E2E_DATA_FILE: ephemeralDataFile(t) }
  )
  const output = outputOf(run)
  assert.match(output, /All specs passed/, `o Cypress precisa ter rodado e aprovado de verdade:\n${output}`)
  assert.equal(run.status, 0, `suite verde precisa sair com zero; saiu com ${run.status}:\n${output}`)
})

test('timeout do Cypress reprova o comando', async (t) => {
  const port = await freePort()
  const run = runHarness(
    'bash',
    ['run-e2e.sh', '--config', harnessConfig, '--spec', 'cypress/harness/passing.cy.js'],
    port,
    { E2E_TIMEOUT: '1', E2E_DATA_FILE: ephemeralDataFile(t) }
  )
  const output = outputOf(run)
  assert.doesNotMatch(output, /dev server did not start/, `a falha nao pode ser do dev server:\n${output}`)
  assert.notEqual(
    run.status,
    0,
    `Cypress interrompido por timeout precisa reprovar o comando; saiu com ${run.status}:\n${output}`
  )
})
