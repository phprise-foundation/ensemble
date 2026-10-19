import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { consoleStore } from '../src/store/console.js'
import { runCommand } from '../src/store/commands.js'
import { boardStore } from '../src/store/board.js'

const realFetch = globalThis.fetch
let fetchCalls = []
let routes = {}

function stubFetch(nextRoutes) {
  routes = nextRoutes
  fetchCalls = []
  globalThis.fetch = async (input, init) => {
    const url = String(input).replace(/^https?:\/\/[^/]+/, '')
    fetchCalls.push({ url, method: init?.method ?? 'GET' })
    const body = routes[url]
    if (body === undefined) {
      return {
        ok: false,
        status: 404,
        statusText: 'Not Found',
        text: async () => JSON.stringify({ error: 'not found' }),
      }
    }
    return { ok: true, status: 200, text: async () => JSON.stringify(body) }
  }
}

after(() => {
  globalThis.fetch = realFetch
})

function agentComment(id, author, body, at) {
  return { id, author, body, at }
}

function boardIssues() {
  return [
    {
      id: 'KANB-1', summary: 'Frame the onboarding intent', statusId: 'to-develop', assigneeId: 'vivy',
      comments: [
        agentComment('c1', 'user', 'We lose users in the first step.', '2026-10-19T09:00:00.000Z'),
        agentComment('c2', 'Vivy', 'Rascunhei o fluxo de onboarding em tres passos.', '2026-10-19T09:05:00.000Z'),
      ],
    },
    {
      id: 'KANB-2', summary: 'Fix the login redirect loop', statusId: 'in-development', assigneeId: 'kaio',
      comments: [
        agentComment('c3', 'David', 'Validei o desenho da grade inferior.', '2026-10-19T09:10:00.000Z'),
      ],
    },
  ]
}

const workspace = {
  boards: [{ id: 'KANB', name: 'Multi-agent', workflowId: 'wf-default', columns: [] }],
}

test('forma da mensagem: append atribui seq monotônico, at, actor, kind e text', () => {
  consoleStore.clear()

  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Primeira linha do log.' })
  consoleStore.append({ actor: 'system', kind: 'system', text: 'Segunda linha.', at: '2026-10-19T09:00:00.000Z' })

  const messages = consoleStore.state.messages
  assert.equal(messages.length, 2, 'cada append entra no log na ordem')
  assert.deepEqual(
    Object.keys(messages[0]).sort(),
    ['actor', 'at', 'kind', 'seq', 'text'],
    'a mensagem carrega exatamente o contrato { seq, at, actor, kind, text }',
  )
  assert.equal(messages[1].seq, messages[0].seq + 1, 'seq é monotônico')
  assert.ok(!Number.isNaN(Date.parse(messages[0].at)), 'at nasce com uma data válida')
  assert.equal(messages[1].at, '2026-10-19T09:00:00.000Z', 'at explícito é respeitado')
  assert.equal(consoleStore.state.unread, 2, 'cada append incrementa unread')

  consoleStore.clearUnread()
  assert.equal(consoleStore.state.unread, 0, 'clearUnread zera o unread sem apagar mensagens')
  assert.equal(consoleStore.state.messages.length, 2, 'clearUnread não toca no log')

  consoleStore.clear()
  assert.equal(consoleStore.state.messages.length, 0, 'clear zera o log')
  assert.equal(consoleStore.state.unread, 0, 'clear zera o unread')
})

test('pollOnce é idempotente: reler as mesmas issues não duplica mensagem', async () => {
  consoleStore.clear()
  const issues = boardIssues()
  stubFetch({ '/api/issues': issues })

  await consoleStore.pollOnce()
  const agentes = consoleStore.state.messages.filter((message) => message.kind === 'agent')
  assert.equal(agentes.length, 2, 'só comentários com author diferente de user viram mensagem')
  assert.deepEqual(
    agentes.map((message) => message.actor),
    ['Vivy', 'David'],
    'o actor da mensagem é o author do comentário',
  )
  assert.equal(
    agentes[0].text,
    'Rascunhei o fluxo de onboarding em tres passos.',
    'o text da mensagem é o body do comentário',
  )

  await consoleStore.pollOnce()
  assert.equal(
    consoleStore.state.messages.filter((message) => message.kind === 'agent').length,
    2,
    'dedupe por issueId:commentId mantém o log estável entre polls',
  )

  issues[0].comments.push(agentComment('c4', 'Vivy', 'Segundo rascunho anexado.', '2026-10-19T09:20:00.000Z'))
  await consoleStore.pollOnce()
  const depois = consoleStore.state.messages.filter((message) => message.kind === 'agent')
  assert.equal(depois.length, 3, 'só o comentário novo entra no poll seguinte')
  assert.equal(depois[2].actor, 'Vivy')
  assert.equal(depois[2].text, 'Segundo rascunho anexado.')
})

test('catálogo fechado: /status, /list e /help respondem system e desconhecido vira error', async () => {
  consoleStore.clear()
  stubFetch({ '/api/workspace': workspace, '/api/issues': boardIssues() })
  await boardStore.load()

  const respostas = {}
  for (const command of ['/help', '/status', '/list']) {
    const antes = consoleStore.state.messages.length
    await runCommand(command)
    const novos = consoleStore.state.messages.slice(antes)
    assert.equal(novos[0].kind, 'command', `${command} registra o eco no log`)
    assert.equal(novos[0].text, command, 'o eco guarda o comando digitado')
    assert.equal(novos[1].kind, 'system', `${command} responde com kind system`)
    assert.ok(novos[1].text.length > 0, `${command} responde com texto no log`)
    respostas[command] = novos[1].text
  }
  assert.ok(respostas['/list'].includes('KANB-1'), '/list lista as issues do board carregado')
  assert.ok(respostas['/status'].length > 0, '/status resume o estado dos agentes')

  const antesErro = consoleStore.state.messages.length
  await runCommand('/rm -rf /')
  const erro = consoleStore.state.messages.slice(antesErro)
  assert.equal(erro[0].kind, 'command', 'comando desconhecido também registra o eco')
  assert.equal(erro[1].kind, 'error', 'comando fora do catálogo responde com kind error')
  assert.ok(
    !erro.some((message) => message.kind === 'system'),
    'nada é executado fora do catálogo fechado',
  )
})

test('/clear é local: zera o log e o unread sem chamada HTTP', async () => {
  consoleStore.clear()
  stubFetch({})
  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'linha que vai sumir' })
  consoleStore.append({ actor: 'system', kind: 'system', text: 'outra linha' })
  assert.equal(consoleStore.state.messages.length, 2)

  const chamadasAntes = fetchCalls.length
  await runCommand('/clear')

  assert.equal(consoleStore.state.messages.length, 0, '/clear esvazia o log')
  assert.equal(consoleStore.state.unread, 0, '/clear zera o unread')
  assert.equal(fetchCalls.length, chamadasAntes, '/clear é ação puramente local, zero HTTP')
})

test('busy impede reenvio: comando enviado durante a execução é ignorado', async () => {
  consoleStore.clear()
  stubFetch({ '/api/workspace': workspace, '/api/issues': boardIssues() })
  await boardStore.load()
  consoleStore.clear()

  const primeira = runCommand('/list')
  const segunda = runCommand('/list')
  await Promise.all([primeira, segunda])

  const ecos = consoleStore.state.messages.filter((message) => message.kind === 'command')
  assert.equal(ecos.length, 1, 'apenas um eco mesmo com dois envios concorrentes')
  assert.equal(consoleStore.state.busy, false, 'busy volta a false depois que o comando termina')
})
