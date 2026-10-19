import assert from 'node:assert/strict'
import { afterEach, beforeEach, test } from 'node:test'

import { api } from '../src/api/index.js'
import { boardStore } from '../src/store/board.js'
import { consoleStore } from '../src/store/console.js'
import { runCommand } from '../src/store/commands.js'

let fetchCalls
let issuesPayload

api.fetchIssues = async () => {
  fetchCalls += 1
  return issuesPayload
}

function seedBoard({ teams = [], statuses = [], issues = [], agents = [] } = {}) {
  Object.assign(boardStore.state, {
    teams,
    statuses,
    issues,
    agents,
    boards: [{ id: 'KANB', workflowId: 'wf' }],
    boardId: 'KANB',
  })
}

function agentComment(issueId, commentId, body) {
  return {
    id: issueId,
    summary: `item ${issueId}`,
    statusId: 's1',
    comments: [{ id: commentId, author: 'Ian', body }],
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
})

test('forma da mensagem: chaves exatas, seq monotônico e kinds do catálogo', () => {
  consoleStore.append('command', 'user', '/help')
  consoleStore.append('system', 'system', 'resposta')
  consoleStore.append('error', 'system', 'falha')

  assert.deepEqual(
    Object.keys(lastMessage()),
    ['seq', 'at', 'kind', 'actor', 'text'],
    'a mensagem não pode ganhar campos extras',
  )
  assert.ok(messages()[1].seq > messages()[0].seq)
  assert.doesNotThrow(() => new Date(messages()[0].at).toISOString())
  for (const message of messages()) {
    assert.ok(['command', 'system', 'agent', 'error'].includes(message.kind))
  }
})

test('pollOnce é idempotente: dedupe issueId:commentId e filtro do autor user', async () => {
  issuesPayload = [
    agentComment('KAN-1', 'c1', 'primeira'),
    agentComment('KAN-1', 'c1', 'primeira'),
    agentComment('KAN-2', 'c2', 'segunda'),
    {
      id: 'KAN-3',
      summary: 'meu item',
      statusId: 's1',
      comments: [{ id: 'c3', author: 'user', body: 'digitado por mim' }],
    },
  ]

  await consoleStore.pollOnce()
  await consoleStore.pollOnce()

  assert.equal(fetchCalls, 2, 'cada pollOnce consulta o endpoint uma vez')
  assert.deepEqual(
    messages().map((message) => message.text),
    ['primeira', 'segunda'],
    'o mesmo comentário não pode entrar duas vezes no log',
  )
  assert.ok(messages().every((message) => message.kind === 'agent'))
})

test('catálogo fechado: os quatro comandos respondem e desconhecido vira error', () => {
  seedBoard({
    statuses: [{ id: 's1', name: 'Coding', workflowId: 'wf', type: 'open' }],
    issues: [{ id: 'KAN-1', summary: 'item ativo', statusId: 's1' }],
  })

  for (const command of ['/status', '/list', '/clear', '/help']) {
    runCommand(command)
    const response = lastMessage()
    assert.equal(response.kind, 'system', `${command} responde com sistema`)
    assert.ok(response.text.length > 0, `${command} responde texto não vazio`)
    assert.equal(response.actor, 'system')
  }

  runCommand('/status')
  assert.ok(lastMessage().text.length > 0, '/status responde mesmo sem agentes registrados')

  runCommand('/deploy')
  const unknown = lastMessage()
  assert.equal(unknown.kind, 'error')
  assert.match(unknown.text, /Comando desconhecido/)
  assert.match(unknown.text, /\/status.*\/list.*\/clear.*\/help/)
})

test('/clear é local: esvazia o log sem nenhuma requisição HTTP', () => {
  consoleStore.append('agent', 'Ian', 'mensagem de agente')
  consoleStore.append('system', 'system', 'resposta')

  runCommand('/clear')

  assert.equal(fetchCalls, 0, '/clear não pode tocar o backend')
  assert.equal(messages().length, 1, 'resta apenas a confirmação local')
  assert.equal(lastMessage().text, 'Console limpo.')
  assert.equal(consoleStore.state.unread, 1)
})

test('busy marca durante o handler, libera depois e trava reenvio', () => {
  let busyDuringHandler = null
  const originalAppend = consoleStore.append
  consoleStore.append = (...args) => {
    busyDuringHandler = consoleStore.state.busy
    return originalAppend(...args)
  }

  try {
    runCommand('/help')
    assert.equal(busyDuringHandler, true, 'busy está marcado enquanto o handler roda')
    assert.equal(consoleStore.state.busy, false, 'busy é liberado ao terminar')

    consoleStore.state.busy = true
    runCommand('/help')
    assert.equal(busyDuringHandler, true, 'o handler roda com busy marcado')
  } finally {
    consoleStore.append = originalAppend
    consoleStore.state.busy = false
  }

  const commandEntries = messages().filter((message) => message.kind === 'command')
  assert.equal(commandEntries.length, 2, 'cada comando entra uma única vez no log')
})
