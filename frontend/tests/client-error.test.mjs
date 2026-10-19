import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { request } from '../src/api/client.js'
import { handle } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

function createBody(overrides = {}) {
  return {
    boardId: 'KANB',
    summary: 'Task do contrato de criação',
    description: '',
    typeId: 'task',
    statusId: 'to-develop',
    assigneeId: null,
    reporter: 'user',
    columnId: 'todo',
    ...overrides,
  }
}

function columnOrders(database, columnId) {
  const board = database.boards.find((candidate) => candidate.id === 'KANB')
  const column = board.columns.find((candidate) => candidate.id === columnId)
  const ids = column.statusIds
  return database.issues
    .filter((issue) => issue.boardId === 'KANB' && ids.includes(issue.statusId))
    .sort((a, b) => a.order - b.order)
    .map((issue) => `${issue.id}:${issue.order}`)
}

const realFetch = globalThis.fetch

after(() => {
  globalThis.fetch = realFetch
})

function stubFetch(status, payload) {
  globalThis.fetch = async () => new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

test('422 de descrição chega ao chamador com status e field', async () => {
  const error = 'A descrição excede o limite de 4095 caracteres (4096).'
  stubFetch(422, { error, field: 'description' })

  await assert.rejects(
    request('PATCH', '/api/issues/KANB-1', { description: 'D'.repeat(4096) }),
    (failure) => {
      assert.equal(failure.status, 422)
      assert.equal(failure.field, 'description')
      assert.equal(failure.message, error)
      return true
    },
  )
})

test('422 de comentário identifica o campo sem parsing da mensagem', async () => {
  stubFetch(422, { error: 'O comentário excede o limite de 1023 caracteres (1024).', field: 'comment' })

  await assert.rejects(
    request('POST', '/api/issues/KANB-1/comments', { body: 'C'.repeat(1024) }),
    (failure) => {
      assert.equal(failure.status, 422)
      assert.equal(failure.field, 'comment')
      return true
    },
  )
})

test('erro sem field no corpo resulta em field nulo', async () => {
  stubFetch(404, { error: 'issue not found' })

  await assert.rejects(request('GET', '/api/issues/KANB-404'), (failure) => {
    assert.equal(failure.status, 404)
    assert.equal(failure.field, null)
    return true
  })
})

test('422 de status inicial chega ao chamador com status e field', async () => {
  const error = 'a nova task precisa nascer em um status inicial'
  stubFetch(422, { error, field: 'statusId' })

  await assert.rejects(
    request('POST', '/api/issues', { summary: 'Task', statusId: 'done' }),
    (failure) => {
      assert.equal(failure.status, 422)
      assert.equal(failure.field, 'statusId')
      assert.equal(failure.message, error)
      return true
    },
  )
})

test('422 de board inválido preserva o field boardId', async () => {
  stubFetch(422, { error: 'board inválido', field: 'boardId' })

  await assert.rejects(
    request('POST', '/api/issues', { summary: 'Task' }),
    (failure) => {
      assert.equal(failure.status, 422)
      assert.equal(failure.field, 'boardId')
      return true
    },
  )
})

test('resposta 2xx devolve o corpo sem status nem field', async () => {
  stubFetch(200, { data: { id: 'KANB-1' } })

  const payload = await request('GET', '/api/issues/KANB-1')

  assert.deepEqual(payload, { data: { id: 'KANB-1' } })
})

test('erro de rede continua sendo o TypeError do fetch, sem status nem field', async () => {
  globalThis.fetch = async () => {
    throw new TypeError('fetch failed')
  }

  await assert.rejects(request('GET', '/api/issues'), (failure) => {
    assert.equal(failure.name, 'TypeError')
    assert.equal(failure.status, undefined)
    assert.equal(failure.field, undefined)
    return true
  })
})

test('handle: POST /issues aceita status initial e deriva a coluna', () => {
  const database = createSeed()

  const response = handle(database, 'POST', ['issues'], createBody({ columnId: 'doing' }))

  assert.equal(response.status, 201)
  assert.equal(response.data.statusId, 'to-develop')
  assert.ok(columnOrders(database, 'todo').some((entry) => entry.split(':')[0] === response.data.id))
  assert.equal('columnId' in response.data, false)
})

test('handle: POST /issues sem statusId usa o primeiro initial do workflow', () => {
  const database = createSeed()

  const response = handle(database, 'POST', ['issues'], createBody({ statusId: undefined }))

  assert.equal(response.status, 201)
  assert.equal(response.data.statusId, 'to-develop')
  assert.equal('columnId' in response.data, false)
})

test('handle: POST /issues rejeita status final, transitional e inexistente com 422', () => {
  const cases = [
    ['done', 'a nova task precisa nascer em um status inicial'],
    ['canceled', 'a nova task precisa nascer em um status inicial'],
    ['in-development', 'a nova task precisa nascer em um status inicial'],
    ['nope', 'status inexistente'],
  ]

  for (const [statusId, expected] of cases) {
    const database = createSeed()
    const before = columnOrders(database, 'todo')

    const response = handle(database, 'POST', ['issues'], createBody({ statusId }))

    assert.equal(response.status, 422)
    assert.equal(response.field, 'statusId')
    assert.equal(response.error, expected)
    assert.deepEqual(columnOrders(database, 'todo'), before)
  }
})

test('handle: POST /issues rejeita status de outro workflow com 422', () => {
  const database = createSeed()
  database.statuses.push({ id: 'external', workflowId: 'other', name: 'External', type: 'initial', color: '#000000', roleIds: [] })
  const before = columnOrders(database, 'todo')

  const response = handle(database, 'POST', ['issues'], createBody({ statusId: 'external' }))

  assert.equal(response.status, 422)
  assert.equal(response.field, 'statusId')
  assert.equal(response.error, 'status de outro workflow')
  assert.deepEqual(columnOrders(database, 'todo'), before)
})

test('handle: POST /issues sem statusId e sem initial no workflow rejeita com 422', () => {
  const database = createSeed()
  const initial = database.statuses.find((status) => status.id === 'to-develop')
  initial.type = 'transitional'
  const before = columnOrders(database, 'todo')

  const response = handle(database, 'POST', ['issues'], createBody({ statusId: undefined }))

  assert.equal(response.status, 422)
  assert.equal(response.field, 'statusId')
  assert.equal(response.error, 'workflow do board não tem status inicial')
  assert.deepEqual(columnOrders(database, 'todo'), before)
})

test('handle: POST /issues com board inválido rejeita com 422 e field boardId', () => {
  const database = createSeed()
  const before = columnOrders(database, 'todo')

  const response = handle(database, 'POST', ['issues'], createBody({ boardId: 'NOPE' }))

  assert.equal(response.status, 422)
  assert.equal(response.field, 'boardId')
  assert.equal(response.error, 'board inválido')
  assert.deepEqual(columnOrders(database, 'todo'), before)
})

test('handle: status válido fora de qualquer coluna do board rejeita com 422', () => {
  const database = createSeed()
  const board = database.boards.find((candidate) => candidate.id === 'KANB')
  board.columns[0].statusIds = []
  const before = columnOrders(database, 'todo')

  const response = handle(database, 'POST', ['issues'], createBody())

  assert.equal(response.status, 422)
  assert.equal(response.field, 'statusId')
  assert.equal(response.error, 'status não está associado a nenhuma coluna do board')
  assert.deepEqual(columnOrders(database, 'todo'), before)
})
