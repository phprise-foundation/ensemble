import assert from 'node:assert/strict'
import { test } from 'node:test'

import { handle } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'
import { COMMENT_LIMIT, DESCRIPTION_LIMIT, countCharacters } from '../shared/limits.js'

const description = (length) => 'D'.repeat(length)
const emoji = (length) => '😀'.repeat(length)
const comment = (length) => 'C'.repeat(length)

function createBody(text) {
  return {
    boardId: 'KANB',
    summary: 'Issue do contrato de limites',
    description: text,
    typeId: 'task',
    statusId: 'to-develop',
    assigneeId: null,
    columnId: 'todo',
  }
}

function legacy(database) {
  const issue = handle(database, 'GET', ['issues', 'KANB-7']).data
  assert.ok(issue, 'createSeed precisa conter a fixture legada KANB-7')
  return issue
}

test('POST /issues aceita 4095 code points e grava o texto integral', () => {
  const database = createSeed()
  const text = description(DESCRIPTION_LIMIT)

  const response = handle(database, 'POST', ['issues'], createBody(text))

  assert.equal(response.status, 201)
  assert.equal(response.data.description, text)
  assert.equal(countCharacters(response.data.description), DESCRIPTION_LIMIT)
  assert.ok(database.issues.some((issue) => issue.id === response.data.id))
})

test('POST /issues rejeita 4096 com 422 sem gravar nem consumir id', () => {
  const database = createSeed()
  const before = database.issues.length

  const rejected = handle(database, 'POST', ['issues'], createBody(description(DESCRIPTION_LIMIT + 1)))

  assert.equal(rejected.status, 422)
  assert.equal(rejected.field, 'description')
  assert.equal(
    rejected.error,
    `A descrição excede o limite de ${DESCRIPTION_LIMIT} caracteres (${DESCRIPTION_LIMIT + 1}).`,
  )
  assert.equal(database.issues.length, before)

  const accepted = handle(database, 'POST', ['issues'], createBody('Texto curto'))
  assert.equal(accepted.data.id, 'KANB-8')
  assert.equal(database.issues.length, before + 1)
})

test('PATCH /issues rejeita 4096 sem alterar descrição, assignee, id nem updatedAt', () => {
  const database = createSeed()
  const issue = database.issues.find((candidate) => candidate.id === 'KANB-1')
  const snapshot = {
    id: issue.id,
    description: issue.description,
    assigneeId: issue.assigneeId,
    updatedAt: issue.updatedAt,
  }

  const rejected = handle(database, 'PATCH', ['issues', 'KANB-1'], {
    description: description(DESCRIPTION_LIMIT + 1),
    assigneeId: 'kaio',
  })

  assert.equal(rejected.status, 422)
  assert.equal(rejected.field, 'description')
  assert.deepEqual(
    {
      id: issue.id,
      description: issue.description,
      assigneeId: issue.assigneeId,
      updatedAt: issue.updatedAt,
    },
    snapshot,
  )
})

test('PATCH /issues aceita 4095 e atualiza o registro', () => {
  const database = createSeed()
  const issue = database.issues.find((candidate) => candidate.id === 'KANB-1')
  const previousUpdatedAt = issue.updatedAt
  const text = description(DESCRIPTION_LIMIT)

  const accepted = handle(database, 'PATCH', ['issues', 'KANB-1'], { description: text })

  assert.equal(accepted.data.description, text)
  assert.equal(issue.description, text)
  assert.notEqual(issue.updatedAt, previousUpdatedAt)
})

test('POST de comentário aceita 1023 e rejeita 1024 sem alterar o registro', () => {
  const database = createSeed()
  const issue = database.issues.find((candidate) => candidate.id === 'KANB-1')
  const previousCount = issue.comments.length
  const previousUpdatedAt = issue.updatedAt

  const rejected = handle(database, 'POST', ['issues', 'KANB-1', 'comments'], {
    author: 'user',
    body: comment(COMMENT_LIMIT + 1),
  })

  assert.equal(rejected.status, 422)
  assert.equal(rejected.field, 'comment')
  assert.equal(
    rejected.error,
    `O comentário excede o limite de ${COMMENT_LIMIT} caracteres (${COMMENT_LIMIT + 1}).`,
  )
  assert.equal(issue.comments.length, previousCount)
  assert.equal(issue.updatedAt, previousUpdatedAt)

  const accepted = handle(database, 'POST', ['issues', 'KANB-1', 'comments'], {
    author: 'user',
    body: comment(COMMENT_LIMIT),
  })

  assert.equal(accepted.data.comments.length, previousCount + 1)
  assert.equal(accepted.data.comments.at(-1).body, comment(COMMENT_LIMIT))
})

test('a contagem é em code points, não em unidades UTF-16', () => {
  const database = createSeed()

  assert.equal(emoji(DESCRIPTION_LIMIT).length, DESCRIPTION_LIMIT * 2)

  const accepted = handle(database, 'POST', ['issues'], createBody(emoji(DESCRIPTION_LIMIT)))
  assert.equal(accepted.status, 201)
  assert.equal(countCharacters(accepted.data.description), DESCRIPTION_LIMIT)

  const rejected = handle(database, 'POST', ['issues'], createBody(emoji(DESCRIPTION_LIMIT + 1)))
  assert.equal(rejected.status, 422)
  assert.equal(rejected.field, 'description')
})

test('a fixture legada é servida inteira', () => {
  const database = createSeed()
  const issue = legacy(database)

  assert.ok(countCharacters(issue.description) > DESCRIPTION_LIMIT)
  assert.ok(issue.description.endsWith('LEGACY-DESCRIPTION-END'))

  const lastComment = issue.comments.at(-1)
  assert.ok(countCharacters(lastComment.body) > COMMENT_LIMIT)
  assert.ok(lastComment.body.endsWith('LEGACY-COMMENT-END'))
})

test('o item legado continua aceitando outros campos e mudança de coluna', () => {
  const database = createSeed()
  legacy(database)

  const patched = handle(database, 'PATCH', ['issues', 'KANB-7'], { assigneeId: 'kaio' })
  assert.equal(patched.data.assigneeId, 'kaio')

  const moved = handle(database, 'POST', ['issues', 'KANB-7', 'move'], {
    columnId: 'doing',
    statusId: 'in-development',
    order: 0,
  })
  assert.equal(moved.issue.statusId, 'in-development')
  assert.equal(moved.issue.description.endsWith('LEGACY-DESCRIPTION-END'), true)
})

test('issue inexistente responde 404 antes de validar o limite', () => {
  const database = createSeed()
  const before = database.issues.length

  const response = handle(database, 'PATCH', ['issues', 'KANB-404'], {
    description: description(DESCRIPTION_LIMIT + 1),
  })

  assert.equal(response.status, 404)
  assert.equal(response.field, undefined)
  assert.equal(database.issues.length, before)
})
