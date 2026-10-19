import assert from 'node:assert/strict'
import { afterEach, beforeEach, test } from 'node:test'

import { api } from '../src/api/index.js'
import { boardStore } from '../src/store/board.js'
import { consoleStore } from '../src/store/console.js'
import { CATALOG, runCommand } from '../src/store/commands.js'
import { pollTick } from '../src/store/console.js'

let fetchCalls
let issuesPayload

api.fetchIssues = async () => {
  fetchCalls += 1
  return issuesPayload
}

function seedBoard({ statuses = [], issues = [], agents = [] } = {}) {
  Object.assign(boardStore.state, {
    statuses,
    issues,
    agents,
    boards: [{ id: 'KANB', workflowId: 'wf' }],
    boardId: 'KANB',
  })
}

function agentComment(issueId, author, created_at, body) {
  return {
    id: issueId,
    summary: `item ${issueId}`,
    statusId: 's1',
    comments: [{ author, body, created_at }],
  }
}

function messages() {
  return consoleStore.state.messages
}

function lastMessage() {
  return messages().at(-1)
}

beforeEach(() => {
  fetchCalls = 0
  issuesPayload = []
  consoleStore.clear()
  seedBoard()
})

afterEach(() => {
  seedBoard()
  consoleStore.resetDedupe()
})

test('forma da mensagem: chaves exatas, seq monotônico e kinds do catálogo', () => {
  consoleStore.append({ actor: 'user', kind: 'command', text: '/help' })
  consoleStore.append({ actor: 'system', kind: 'system', text: 'resposta' })
  consoleStore.append({ actor: 'system', kind: 'error', text: 'falha' })

  assert.deepEqual(
    Object.keys(lastMessage()),
    ['seq', 'at', 'actor', 'kind', 'text'],
    'a mensagem não pode ganhar campos extras',
  )
  assert.ok(messages()[1].seq > messages()[0].seq)
  assert.doesNotThrow(() => new Date(messages()[0].at).toISOString())
  for (const message of messages()) {
    assert.ok(['command', 'system', 'agent', 'error'].includes(message.kind))
  }
})

test('append respeita o "at" explícito quando recebido', () => {
  consoleStore.append({ actor: 'Ian', kind: 'agent', text: 'linha', at: '2026-10-19T09:00:00.000Z' })
  assert.equal(lastMessage().at, '2026-10-19T09:00:00.000Z')
})

test('pollOnce lê o payload real: vários comentários por issue, novos entram, user fica fora', async () => {
  issuesPayload = [
    {
      id: 'KAN-1',
      summary: 'item KAN-1',
      statusId: 's1',
      comments: [
        { author: 'Ian', body: 'primeira', created_at: '2026-10-19T10:00:00+00:00' },
        { author: 'Ian', body: 'segunda', created_at: '2026-10-19T10:05:00+00:00' },
        { author: 'user', body: 'digitado por mim', created_at: '2026-10-19T10:06:00+00:00' },
      ],
    },
    {
      id: 'KAN-2',
      summary: 'item KAN-2',
      statusId: 's1',
      comments: [{ author: 'Alan', body: 'revisão', created_at: '2026-10-19T10:07:00+00:00' }],
    },
  ]

  await consoleStore.pollOnce()

  assert.equal(fetchCalls, 1)
  assert.deepEqual(messages().map((message) => message.text), ['primeira', 'segunda', 'revisão'])
  assert.equal(messages()[0].at, '2026-10-19T10:00:00+00:00')
  assert.ok(messages().every((message) => message.kind === 'agent'))

  issuesPayload[0].comments.push({ author: 'Ian', body: 'nova', created_at: '2026-10-19T11:00:00+00:00' })
  await consoleStore.pollOnce()

  assert.deepEqual(
    messages().map((message) => message.text),
    ['primeira', 'segunda', 'revisão', 'nova'],
    'comentário novo entra no poll seguinte',
  )
})

test('pollOnce é idempotente e serializa chamadas concorrentes', async () => {
  issuesPayload = [
    agentComment('KAN-1', 'Ian', '2026-10-19T10:00:00+00:00', 'primeira'),
    agentComment('KAN-2', 'Alan', '2026-10-19T10:00:00+00:00', 'segunda'),
  ]

  await Promise.all([consoleStore.pollOnce(), consoleStore.pollOnce()])
  await consoleStore.pollOnce()

  assert.equal(fetchCalls, 2, 'polls sobrepostos compartilham a mesma requisição')
  assert.deepEqual(messages().map((message) => message.text), ['primeira', 'segunda'])
})

test('falha de rede vira mensagem error sem derrubar a store', async () => {
  issuesPayload = []
  const original = api.fetchIssues
  api.fetchIssues = async () => {
    fetchCalls += 1
    throw new Error('HTTP 500')
  }

  try {
    await assert.doesNotReject(() => consoleStore.pollOnce())
  } finally {
    api.fetchIssues = original
  }

  assert.equal(lastMessage().kind, 'error')
  assert.match(lastMessage().text, /HTTP 500/)
})

test('catálogo fechado: os quatro comandos respondem e desconhecido vira error', async () => {
  seedBoard({
    statuses: [
      { id: 's1', name: 'Coding', workflowId: 'wf', type: 'open' },
      { id: 's2', name: 'Done', workflowId: 'wf', type: 'final' },
    ],
    issues: [
      { id: 'KAN-1', summary: 'item ativo', statusId: 's1' },
      { id: 'KAN-2', summary: 'item final', statusId: 's2' },
    ],
  })

  assert.deepEqual(CATALOG, ['/status', '/list', '/clear', '/help'])

  for (const command of ['/status', '/list', '/help']) {
    await runCommand(command)
    const response = lastMessage()
    assert.equal(response.kind, 'system', `${command} responde com sistema`)
    assert.ok(response.text.length > 0, `${command} responde texto não vazio`)
    assert.equal(response.actor, 'system')
  }

  await runCommand('/status')
  assert.match(lastMessage().text, /nenhum agente registrado/)
  assert.match(lastMessage().text, /Coding: KAN-1/)

  await runCommand('/list')
  assert.match(lastMessage().text, /KAN-1/)
  assert.doesNotMatch(lastMessage().text, /KAN-2/)

  await runCommand('/deploy')
  const unknown = lastMessage()
  assert.equal(unknown.kind, 'error')
  assert.match(unknown.text, /Comando desconhecido/)
  assert.match(unknown.text, /\/status.*\/list.*\/clear.*\/help/)
})

test('/clear é local: zera log e unread sem entrar no log nem tocar o backend', async () => {
  consoleStore.append({ actor: 'Ian', kind: 'agent', text: 'mensagem de agente' })
  consoleStore.append({ actor: 'system', kind: 'system', text: 'resposta' })

  await runCommand('/clear')

  assert.equal(fetchCalls, 0, '/clear não pode tocar o backend')
  assert.equal(messages().length, 0, 'o contrato manda zerar o log')
  assert.equal(consoleStore.state.unread, 0)
  assert.equal(consoleStore.state.busy, false)
})

test('busy é observável durante o comando e impede reenvio', async () => {
  const first = runCommand('/help')
  assert.equal(consoleStore.state.busy, true, 'busy fica marcado após o runCommand síncrono')

  await Promise.all([first, runCommand('/list')])
  consoleStore.state.busy = true
  await runCommand('/status')
  consoleStore.state.busy = false
  assert.equal(consoleStore.state.busy, false, 'busy é liberado ao terminar')

  const echos = messages().filter((message) => message.kind === 'command')
  assert.equal(echos.length, 1, 'o reenvio durante busy não gera eco')
})

test('polling com a rede fora não vaza rejeição solta entre ciclos', async () => {
  const rejections = []
  const onFailure = (reason) => rejections.push(reason?.message ?? reason)
  process.on('unhandledRejection', onFailure)
  process.on('uncaughtException', onFailure)

  const original = api.fetchIssues
  let tick = null
  try {
    globalThis.window = {
      setInterval: (callback, interval) => {
        tick = callback
        assert.equal(interval, 5000)
        return 1
      },
      clearInterval: () => {},
    }
    globalThis.document = { hidden: false, addEventListener: () => {}, removeEventListener: () => {} }
    api.fetchIssues = async () => {
      throw new Error('rede caiu')
    }

    consoleStore.start()
    assert.equal(consoleStore.state.polling, true)

    await pollTick()
    await pollTick()
    await new Promise((resolve) => setTimeout(resolve, 20))

    assert.deepEqual(rejections, [], 'o ciclo descartado não pode soltar promise')
    assert.equal(tick, pollTick)
    const failures = messages().filter((message) => message.kind === 'error')
    assert.equal(failures.length, 1, 'falha contínua aparece uma única vez no log')
    assert.match(failures[0].text, /rede caiu/)
  } finally {
    consoleStore.stop()
    api.fetchIssues = original
    delete globalThis.window
    delete globalThis.document
    process.off('unhandledRejection', onFailure)
    process.off('uncaughtException', onFailure)
  }
})
