import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { handle } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

const root = mkdtempSync(join(tmpdir(), 'kan-11-'))

after(() => {
  rmSync(root, { recursive: true, force: true })
})

function directory(path) {
  const absolute = join(root, path)
  mkdirSync(absolute, { recursive: true })
  return absolute
}

function file(path) {
  const absolute = join(root, path)
  writeFileSync(absolute, '')
  return absolute
}

function missing(...segments) {
  return join(root, ...segments)
}

function environment() {
  const database = createSeed()
  const client = (method, url, body = {}) => {
    const segments = url.replace(/^\/api\/?/, '').split('/').filter(Boolean)
    const result = handle(database, method, segments, body)
    return { ...result, status: result.status ?? 200 }
  }
  return { client }
}

test('cria source com diretório válido e nome indicado', () => {
  const { client } = environment()
  const path = directory('real/projeto real')
  const created = client('POST', '/api/sources', { name: 'Projeto Real', path, workspaceId: 'default' })

  assert.equal(created.status, 201)
  assert.equal(created.data.name, 'Projeto Real')
  assert.equal(created.data.workspaceId, 'default')
  assert.deepEqual(client('GET', '/api/sources').data, [created.data])
})

test('deriva nome e id do último diretório quando o nome vem vazio', () => {
  const { client } = environment()
  const created = client('POST', '/api/sources', {
    name: '',
    path: `${directory('aninhado/projetos')}/`,
    workspaceId: 'default',
  })

  assert.equal(created.status, 201)
  assert.equal(created.data.name, 'projetos')
  assert.equal(created.data.id, 'projetos')
})

test('recusa caminho vazio com a causa verdadeira', () => {
  const { client } = environment()
  const empty = client('POST', '/api/sources', { name: 'Sem caminho', path: '', workspaceId: 'default' })
  const absent = client('POST', '/api/sources', { name: 'Sem caminho', workspaceId: 'default' })

  assert.equal(empty.status, 422)
  assert.equal(empty.error, 'informe o caminho do diretório')
  assert.equal(absent.status, 422)
  assert.equal(absent.error, 'informe o caminho do diretório')
  assert.deepEqual(client('GET', '/api/sources').data, [])
})

test('recusa caminho inexistente com a causa verdadeira', () => {
  const { client } = environment()
  const refused = client('POST', '/api/sources', {
    name: 'Fantasma',
    path: missing('inexistente', 'kan-11'),
    workspaceId: 'default',
  })

  assert.equal(refused.status, 422)
  assert.equal(refused.error, 'diretório não encontrado')
  assert.deepEqual(client('GET', '/api/sources').data, [])
})

test('recusa caminho que não é diretório', () => {
  const { client } = environment()
  const refused = client('POST', '/api/sources', { name: 'Arquivo', path: file('leia-me.md'), workspaceId: 'default' })

  assert.equal(refused.status, 422)
  assert.equal(refused.error, 'diretório não encontrado')
})

test('registra em repetição com diretórios distintos sem atrito', () => {
  const { client } = environment()
  const first = client('POST', '/api/sources', { name: 'Primeiro', path: directory('distinto/um'), workspaceId: 'default' })
  const second = client('POST', '/api/sources', { name: 'Segundo', path: directory('distinto/dois'), workspaceId: 'default' })

  assert.equal(first.status, 201)
  assert.equal(second.status, 201)
  assert.deepEqual(
    client('GET', '/api/sources').data.map((element) => element.id),
    [first.data.id, second.data.id]
  )
})

test('recusa duplicata deliberada com 409', () => {
  const { client } = environment()
  const path = directory('duplicata')
  const original = client('POST', '/api/sources', { name: '', path, workspaceId: 'default' })
  const copy = client('POST', '/api/sources', { name: '', path, workspaceId: 'default' })

  assert.equal(original.status, 201)
  assert.equal(copy.status, 409)
  assert.equal(copy.error, 'source id já existe')
  assert.deepEqual(
    client('GET', '/api/sources').data.map((element) => element.id),
    [original.data.id]
  )
})

test('recusa pastas distintas com o mesmo nome com 409', () => {
  const { client } = environment()
  const original = client('POST', '/api/sources', { name: '', path: directory('colisao/a/projetos'), workspaceId: 'default' })
  const copy = client('POST', '/api/sources', { name: '', path: directory('colisao/b/projetos'), workspaceId: 'default' })

  assert.equal(original.status, 201)
  assert.equal(copy.status, 409)
  assert.equal(copy.error, 'source id já existe')
})

test('renomeia por PATCH mantendo o id estável', () => {
  const { client } = environment()
  const path = directory('renomeada')
  client('POST', '/api/sources', { name: '', path, workspaceId: 'default' })
  const renamed = client('PATCH', '/api/sources/renomeada', { name: 'Renomeada', path })

  assert.equal(renamed.status, 200)
  assert.equal(renamed.data.id, 'renomeada')
  assert.equal(renamed.data.name, 'Renomeada')
  assert.deepEqual(client('GET', '/api/sources').data, [renamed.data])
})

test('recusa PATCH de source inexistente com 404', () => {
  const { client } = environment()
  const validPath = client('PATCH', '/api/sources/nao-sou', { name: 'X', path: directory('qualquer') })
  const invalidPath = client('PATCH', '/api/sources/nao-sou', { name: 'X', path: missing('nao', 'kan-11') })

  assert.equal(validPath.status, 404)
  assert.equal(validPath.error, 'source não encontrada')
  assert.equal(invalidPath.status, 404)
  assert.equal(invalidPath.error, 'source não encontrada')
})

test('recusa PATCH com caminho inexistente e mantém a source intacta', () => {
  const { client } = environment()
  const path = directory('mantida')
  client('POST', '/api/sources', { name: '', path, workspaceId: 'default' })
  const refused = client('PATCH', '/api/sources/mantida', { name: 'Mudada', path: missing('nao', 'kan-11') })

  assert.equal(refused.status, 422)
  assert.equal(refused.error, 'diretório não encontrado')
  const intact = client('GET', '/api/sources').data[0]
  assert.equal(intact.path, path)
  assert.equal(intact.name, 'mantida')
})

test('remove source existente', () => {
  const { client } = environment()
  client('POST', '/api/sources', { name: '', path: directory('removida'), workspaceId: 'default' })
  const removed = client('DELETE', '/api/sources/removida')

  assert.equal(removed.status, 200)
  assert.equal(removed.data.deleted, 'removida')
  assert.deepEqual(client('GET', '/api/sources').data, [])
})
