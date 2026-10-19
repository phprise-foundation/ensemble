import assert from 'node:assert/strict'
import { afterEach, beforeEach, test } from 'node:test'

import { api } from '../src/api/index.js'
import { boardStore } from '../src/store/board.js'
import { consoleStore, pollTick } from '../src/store/console.js'
import { CATALOG, runCommand } from '../src/store/commands.js'

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

const STATUSES = [
  { id: 'draft', name: 'Rascunho', workflowId: 'wf', type: 'initial' },
  { id: 'coding', name: 'Coding', workflowId: 'wf', type: 'transitional' },
  { id: 'done', name: 'Done', workflowId: 'wf', type: 'final' },
]

const AGENTS = [{ id: 'ian', name: 'Ian' }, { id: 'alan', name: 'Alan' }]

const COMMENT_BY_ID = 0
const COMMENT_BY_STAMP = 1

function fakeBackendPayload(form) {
  const comments = [
    { id: 'c1', author: form === COMMENT_BY_ID ? 'Ian' : 'user', body: 'primeira', at: '2026-10-19T10:00:00.000Z' },
    { id: 'c2', author: 'Ian', body: 'segunda', at: '2026-10-19T10:00:00.000Z' },
    { id: 'c3', author: 'user', body: 'digitado por mim', at: '2026-10-19T10:06:00.000Z' },
  ]
  return [
    { id: 'KANB-1', summary: 'item KANB-1', statusId: 'coding', comments },
    {
      id: 'KANB-2',
      summary: 'item KANB-2',
      statusId: 'draft',
      comments: [{ id: 'c4', author: 'Alan', body: 'revisão', at: '2026-10-19T10:07:00.000Z' }],
    },
  ]
}

function presenterPayload() {
  return [
    {
      id: 64,
      title: 'Store do console',
      status: 'coding',
      comments: [
        { author: 'Ian', body: 'primeira', created_at: '2026-10-19T10:00:00+00:00' },
        { author: 'Ian', body: 'segunda', created_at: '2026-10-19T10:00:00+00:00' },
        { author: 'user', body: 'digitado por mim', created_at: '2026-10-19T10:06:00+00:00' },
      ],
    },
    {
      id: 65,
      title: 'Outro item',
      status: 'done',
      comments: [{ author: 'Alan', body: 'revisão', created_at: '2026-10-19T10:07:00+00:00' }],
    },
  ]
}

function texts() {
  return consoleStore.state.messages.map((message) => message.text)
}

function lastMessage() {
  return consoleStore.state.messages.at(-1)
}

beforeEach(() => {
  fetchCalls = 0
  issuesPayload = []
  consoleStore.clear()
  consoleStore.resetDedupe()
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

  assert.deepEqual(Object.keys(lastMessage()), ['seq', 'at', 'actor', 'kind', 'text'])
  assert.ok(consoleStore.state.messages[1].seq > consoleStore.state.messages[0].seq)
  assert.doesNotThrow(() => new Date(consoleStore.state.messages[0].at).toISOString())
  for (const message of consoleStore.state.messages) {
    assert.ok(['command', 'system', 'agent', 'error'].includes(message.kind))
  }
})

test('append respeita o "at" explícito quando recebido', () => {
  consoleStore.append({ actor: 'Ian', kind: 'agent', text: 'linha', at: '2026-10-19T09:00:00.000Z' })
  assert.equal(lastMessage().at, '2026-10-19T09:00:00.000Z')
})

test('payload do fake backend com id: idempotente, novos entram, user fica fora', async () => {
  issuesPayload = fakeBackendPayload(COMMENT_BY_ID)

  await consoleStore.pollOnce()
  await consoleStore.pollOnce()

  assert.equal(fetchCalls, 2)
  assert.deepEqual(texts(), ['primeira', 'segunda', 'revisão'])
  assert.equal(consoleStore.state.messages[0].at, '2026-10-19T10:00:00.000Z')
  assert.ok(consoleStore.state.messages.every((message) => message.kind === 'agent'))

  issuesPayload[0].comments.push({ id: 'c5', author: 'Ian', body: 'nova', at: '2026-10-19T11:00:00.000Z' })
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['primeira', 'segunda', 'revisão', 'nova'])
})

test('payload do fake backend sem id: fallback por autor, instante e texto', async () => {
  issuesPayload = fakeBackendPayload(COMMENT_BY_STAMP)

  await consoleStore.pollOnce()
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['segunda', 'revisão'])

  issuesPayload[0].comments.push({ author: 'Alan', body: 'nova', at: '2026-10-19T10:00:00.000Z' })
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['segunda', 'revisão', 'nova'])
})

test('payload do presenter PHP (created_at, mesmo segundo): nada se perde, nada duplica', async () => {
  issuesPayload = presenterPayload()

  await consoleStore.pollOnce()
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['primeira', 'segunda', 'revisão'])
  assert.equal(consoleStore.state.messages[0].at, '2026-10-19T10:00:00+00:00')

  issuesPayload[0].comments.push({ author: 'Ian', body: 'nova', created_at: '2026-10-19T11:00:00+00:00' })
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['primeira', 'segunda', 'revisão', 'nova'])
})

test('comentários idênticos sem id no mesmo lote entram os dois; releitura não duplica', async () => {
  const comment = { author: 'Ian', body: 'ok', created_at: '2026-10-19T10:00:00+00:00' }
  issuesPayload = [{ id: 64, title: 'item', status: 'coding', comments: [comment, { ...comment }, comment] }]

  await consoleStore.pollOnce()
  assert.deepEqual(texts(), ['ok', 'ok', 'ok'])

  await consoleStore.pollOnce()
  assert.deepEqual(texts(), ['ok', 'ok', 'ok'])
})

test('releitura de payload sem id nem instante não duplica o mesmo comentário', async () => {
  issuesPayload = [{ id: 64, title: 'item', status: 'coding', comments: [{ author: 'Alan', body: 'revisão' }] }]

  await consoleStore.pollOnce()
  await consoleStore.pollOnce()

  assert.deepEqual(texts(), ['revisão'])
})

test('falha de rede vira mensagem error única sem derrubar a store', async () => {
  const original = api.fetchIssues
  api.fetchIssues = async () => {
    fetchCalls += 1
    throw new Error('HTTP 500')
  }

  try {
    await assert.doesNotReject(() => consoleStore.pollOnce())
    await assert.doesNotReject(() => consoleStore.pollOnce())
  } finally {
    api.fetchIssues = original
  }

  assert.equal(consoleStore.state.messages.filter((message) => message.kind === 'error').length, 1)
  assert.match(lastMessage().text, /HTTP 500/)
})

test('pollOnce concorrente compartilha a requisição e nunca rejeita', async () => {
  issuesPayload = presenterPayload()

  await Promise.all([consoleStore.pollOnce(), consoleStore.pollOnce()])
  await consoleStore.pollOnce()

  assert.equal(fetchCalls, 2)
  assert.deepEqual(texts(), ['primeira', 'segunda', 'revisão'])
})

test('/status e /list rendem com payload camelCase do boardStore', async () => {
  seedBoard({
    statuses: STATUSES,
    agents: AGENTS,
    issues: [
      { id: 'KAN-1', summary: 'item ativo', statusId: 'coding' },
      { id: 'KAN-2', summary: 'item final', statusId: 'done' },
    ],
  })

  await runCommand('/status')
  assert.match(lastMessage().text, /Agentes: Ian, Alan/)
  assert.match(lastMessage().text, /Coding: 1/)
  assert.match(lastMessage().text, /Done: 1/)

  await runCommand('/list')
  assert.match(lastMessage().text, /KAN-1 · item ativo · Coding/)
  assert.doesNotMatch(lastMessage().text, /KAN-2/)
})

test('/status e /list rendem com issue do presenter PHP (title/status)', async () => {
  seedBoard({
    statuses: STATUSES,
    agents: AGENTS,
    issues: [
      { id: 64, title: 'Store do console', status: 'coding' },
      { id: 65, title: 'Outro', status: 'done' },
    ],
  })

  await runCommand('/status')
  assert.match(lastMessage().text, /Coding: 1/)
  assert.doesNotMatch(lastMessage().text, /undefined/)

  await runCommand('/list')
  assert.match(lastMessage().text, /64 · Store do console · Coding/)
  assert.doesNotMatch(lastMessage().text, /undefined/)
  assert.doesNotMatch(lastMessage().text, /65/)
})

test('polling descarta consulta com a aba oculta', async () => {
  globalThis.document = { hidden: true }
  try {
    await pollTick()
  } finally {
    delete globalThis.document
  }

  assert.equal(fetchCalls, 0)
})

test('/status responde com registro de agentes vazio', async () => {
  await runCommand('/status')
  assert.equal(lastMessage().kind, 'system')
  assert.match(lastMessage().text, /nenhum agente registrado/)
})

test('catálogo fechado, /help lista e desconhecido vira error sugerindo /help', async () => {
  assert.deepEqual(CATALOG, ['/status', '/list', '/clear', '/help'])

  await runCommand('/help')
  assert.equal(lastMessage().kind, 'system')
  assert.match(lastMessage().text, /\/status.*\/list.*\/clear.*\/help/)

  await runCommand('/deploy')
  const unknown = lastMessage()
  assert.equal(unknown.kind, 'error')
  assert.match(unknown.text, /Comando desconhecido/)
  assert.match(unknown.text, /\/help/)
})

test('/clear é local: zera log e unread sem eco nem HTTP', async () => {
  consoleStore.append({ actor: 'Ian', kind: 'agent', text: 'mensagem de agente' })
  consoleStore.append({ actor: 'system', kind: 'system', text: 'resposta' })

  await runCommand('/clear')

  assert.equal(fetchCalls, 0)
  assert.equal(consoleStore.state.messages.length, 0)
  assert.equal(consoleStore.state.unread, 0)
  assert.equal(consoleStore.state.busy, false)
})

test('busy é observável durante o comando e impede reenvio', async () => {
  const first = runCommand('/help')
  assert.equal(consoleStore.state.busy, true)

  await Promise.all([first, runCommand('/list')])
  consoleStore.state.busy = true
  await runCommand('/status')
  consoleStore.state.busy = false
  assert.equal(consoleStore.state.busy, false)

  const echos = consoleStore.state.messages.filter((message) => message.kind === 'command')
  assert.equal(echos.length, 1)
})

test('polling com a rede fora não vaza rejeição solta entre ciclos', async () => {
  const rejections = []
  const onFailure = (reason) => rejections.push(reason?.message ?? reason)
  process.on('unhandledRejection', onFailure)
  process.on('uncaughtException', onFailure)

  const original = api.fetchIssues
  let scheduled = null
  try {
    globalThis.window = {
      setInterval: (callback, interval) => {
        scheduled = callback
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

    assert.deepEqual(rejections, [])
    assert.equal(scheduled, pollTick)
    const failures = consoleStore.state.messages.filter((message) => message.kind === 'error')
    assert.equal(failures.length, 1)
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
