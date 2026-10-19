import assert from 'node:assert/strict'
import { test } from 'node:test'

import { handle } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

const createBody = (overrides = {}) => ({
  boardId: 'KANB',
  summary: 'Issue do contrato de status inicial',
  description: 'Criada para validar o status inicial.',
  typeId: 'task',
  statusId: 'to-develop',
  assigneeId: null,
  ...overrides,
})

function nextIssueId(database) {
  const highest = database.issues.reduce((max, issue) => {
    if (issue.boardId !== 'KANB') return max
    const match = /-(\d+)$/.exec(issue.id)
    return match ? Math.max(max, Number(match[1])) : max
  }, 0)
  return `KANB-${highest + 1}`
}

function addStatus(database, status) {
  database.statuses.push({ workflowId: 'standard', color: '#4c9aff', ...status })
}

function rejected(database, body) {
  const before = { count: database.issues.length, next: nextIssueId(database) }
  const response = handle(database, 'POST', ['issues'], createBody(body))
  assert.equal(response.status, 422)
  return { response, before }
}

function rejectedByField(database, body, field) {
  const { response, before } = rejected(database, body)
  assert.equal(response.field, field)
  assert.equal(database.issues.length, before.count)
  return before
}

function rejectionWithoutConsequences(database, body) {
  const before = rejectedByField(database, body, 'statusId')
  const accepted = handle(database, 'POST', ['issues'], createBody())
  assert.equal(accepted.status, 201)
  assert.equal(accepted.data.id, before.next)
}

test('POST com statusId initial grava a issue sem columnId e derivando a ordem da coluna', () => {
  const database = createSeed()
  const next = nextIssueId(database)

  const response = handle(database, 'POST', ['issues'], createBody())

  assert.equal(response.status, 201)
  assert.equal(response.data.statusId, 'to-develop')
  assert.equal(response.data.id, next)
  assert.equal('columnId' in response.data, false)
  const orders = database.issues
    .filter((issue) => issue.statusId === 'to-develop')
    .sort((a, b) => a.order - b.order)
    .map((issue) => issue.order)
  assert.deepEqual(orders, [...orders.keys()])
})

test('POST com statusId final é rejeitado com 422 field=statusId, nada gravado e id preservado', () => {
  rejectionWithoutConsequences(createSeed(), { statusId: 'done' })
})

test('POST com statusId transitional é rejeitado com 422 field=statusId, nada gravado e id preservado', () => {
  rejectionWithoutConsequences(createSeed(), { statusId: 'in-development' })
})

test('POST com statusId inexistente é rejeitado com 422 field=statusId', () => {
  rejectionWithoutConsequences(createSeed(), { statusId: 'no-such-status' })
})

test('POST com statusId de outro workflow é rejeitado com 422 field=statusId', () => {
  const database = createSeed()
  addStatus(database, { id: 'fast-track', workflowId: 'accelerated', type: 'initial' })
  rejectionWithoutConsequences(database, { statusId: 'fast-track' })
})

test('POST sem statusId nasce com o primeiro initial da ordem do workflow', () => {
  const database = createSeed()

  const response = handle(database, 'POST', ['issues'], createBody({ statusId: undefined }))

  assert.equal(response.status, 201)
  assert.equal(response.data.statusId, 'to-develop')
})

test('POST sem statusId em workflow sem initial responde 422 mencionando "inicial", sem gravar e sem consumir o id', () => {
  const database = createSeed()
  const only = database.statuses.find((status) => status.id === 'to-develop')
  only.type = 'transitional'
  const id = nextIssueId(database)

  const response = handle(database, 'POST', ['issues'], createBody({ statusId: undefined }))

  assert.equal(response.status, 422)
  assert.equal(response.field, 'statusId')
  assert.ok(response.error.includes('inicial'))
  assert.ok(database.issues.every((issue) => issue.id !== id))
  assert.equal(database.issues.length, 7)
})

test('POST com boardId inexistente responde 422 field=boardId, sem fallback e sem gravar', () => {
  const database = createSeed()
  const before = rejectedByField(database, { boardId: 'OTHER' }, 'boardId')

  const missing = handle(database, 'POST', ['issues'], createBody({ boardId: undefined }))
  assert.equal(missing.status, 422)
  assert.equal(missing.field, 'boardId')

  const accepted = handle(database, 'POST', ['issues'], createBody())
  assert.equal(accepted.status, 201)
  assert.equal(accepted.data.id, before.next)
  assert.equal(database.issues.length, before.count + 1)
})

test('columnId forjado é ignorado: a issue fica no status initial e a coluna não muda', () => {
  const database = createSeed()
  const doneOrder = database.issues
    .filter((issue) => issue.statusId === 'done')
    .map((issue) => [issue.id, issue.order])

  const response = handle(database, 'POST', ['issues'], createBody({ columnId: 'done' }))

  assert.equal(response.status, 201)
  assert.equal(response.data.statusId, 'to-develop')
  assert.equal('columnId' in response.data, false)
  const doneAfter = database.issues
    .filter((issue) => issue.statusId === 'done')
    .map((issue) => [issue.id, issue.order])
  assert.deepEqual(doneAfter, doneOrder)
})

test('status inicial sem coluna responde 422 field=statusId, sem gravar e sem consumir o id', () => {
  const database = createSeed()
  addStatus(database, { id: 'loose', workflowId: 'standard', type: 'initial' })
  rejectionWithoutConsequences(database, { statusId: 'loose' })
})

test('com múltiplos initial, o default é o primeiro da ordem do workflow', () => {
  const database = createSeed()
  addStatus(database, { id: 'fast-track', workflowId: 'standard', type: 'initial' })
  database.boards
    .find((board) => board.id === 'KANB')
    .columns[0].statusIds.unshift('fast-track')

  const explicit = handle(database, 'POST', ['issues'], createBody())
  const omitted = handle(database, 'POST', ['issues'], createBody({ statusId: undefined }))

  assert.equal(explicit.data.statusId, 'to-develop')
  assert.equal(omitted.status, 201)
  assert.equal(omitted.data.statusId, 'to-develop')
})
