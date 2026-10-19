import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { request } from '../src/api/client.js'

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
