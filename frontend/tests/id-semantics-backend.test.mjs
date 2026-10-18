import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { test } from 'node:test'

import { handle } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

function freshDb() {
  return createSeed()
}

test('PATCH de coleção com id divergente no corpo é recusado com 409 e causa verdadeira', () => {
  const db = freshDb()

  const response = handle(db, 'PATCH', ['statuses', 'in-development'], { id: 'hacked', name: 'Renomeado' })

  assert.deepEqual(response, { error: 'statuses id divergente da URL: o id é imutável', status: 409 })
  const record = db.statuses.find((candidate) => candidate.id === 'in-development')
  assert.ok(record, 'o registro permanece com o id da URL')
  assert.equal(record.name, 'In Development')
  assert.ok(!db.statuses.some((candidate) => candidate.id === 'hacked'), 'nenhum registro assume a chave do corpo')
})

test('PATCH de source com id divergente no corpo é recusado com 409 e causa verdadeira', () => {
  const db = freshDb()
  db.sources.push({ id: 'proj', name: 'Proj', path: tmpdir(), workspaceId: 'default' })

  const response = handle(db, 'PATCH', ['sources', 'proj'], { id: 'outro', name: 'Renomeado' })

  assert.deepEqual(response, { error: 'sources id divergente da URL: o id é imutável', status: 409 })
  const record = db.sources.find((candidate) => candidate.id === 'proj')
  assert.ok(record, 'o registro permanece com o id da URL')
  assert.equal(record.name, 'Proj')
  assert.ok(!db.sources.some((candidate) => candidate.id === 'outro'), 'nenhum registro assume a chave do corpo')
})

test('PATCH de issue com id divergente no corpo fixa a chave, sem mudar o id', () => {
  const db = freshDb()

  const response = handle(db, 'PATCH', ['issues', 'KANB-1'], { id: 'HACKED', summary: 'Resumo novo' })

  assert.equal(response.data.id, 'KANB-1')
  const record = db.issues.find((candidate) => candidate.id === 'KANB-1')
  assert.ok(record, 'o registro permanece com o id da URL')
  assert.equal(record.summary, 'Resumo novo')
  assert.ok(!db.issues.some((candidate) => candidate.id === 'HACKED'), 'nenhum registro assume a chave do corpo')
})

test('POST de coleção não persiste o flag de transporte __existing', () => {
  const db = freshDb()

  const response = handle(db, 'POST', ['workflows'], { id: 'wf-x', name: 'X', workspaceId: 'default', __existing: true })

  assert.equal(response.status, 201)
  assert.ok(!('__existing' in response.data), 'a resposta não carrega o flag')
  const record = db.workflows.find((candidate) => candidate.id === 'wf-x')
  assert.ok(record, 'o registro é gravado')
  assert.ok(!('__existing' in record), 'o flag nunca vaza pela API')
})

test('POST de source não persiste o flag de transporte __existing', () => {
  const db = freshDb()
  const directory = mkdtempSync(join(tmpdir(), 'kanban-id-semantics-'))

  const response = handle(db, 'POST', ['sources'], { id: '', name: '', path: directory, __existing: true })

  assert.equal(response.status, 201)
  assert.ok(!('__existing' in response.data), 'a resposta não carrega o flag')
  const record = db.sources.find((candidate) => candidate.path === directory)
  assert.ok(record, 'a source é gravada com o diretório válido')
  assert.ok(!('__existing' in record), 'o flag nunca vaza pela API')
})
