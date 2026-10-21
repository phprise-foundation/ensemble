import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { handle, load } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

const root = mkdtempSync(join(tmpdir(), 'kan-56-'))
let fileCount = 0

after(() => {
  rmSync(root, { recursive: true, force: true })
})

function databaseFile(database) {
  const file = join(root, `db-${fileCount}.json`)
  fileCount += 1
  writeFileSync(file, JSON.stringify(database, null, 2))
  return file
}

function legacyDatabase() {
  const database = createSeed()
  delete database.user
  database.workspaces = [{ id: 'legacy', name: 'Legacy workspace' }]
  return database
}

test('backfill: banco antigo sem user ganha a chave e /api/user volta a responder', () => {
  const database = load(databaseFile(legacyDatabase()))

  assert.deepEqual(
    database.user,
    { login: 'admin', displayName: '', avatar: null, avatarColor: '#e5484d' },
    'a chave ausente do seed é acrescentada na leitura, já com avatar e cor',
  )

  const fetched = handle(database, 'GET', ['user'], {})
  assert.deepEqual(
    fetched,
    { data: { login: 'admin', displayName: '', avatar: null, avatarColor: '#e5484d' } },
    'GET /api/user responde 200 com os quatro campos depois do backfill',
  )

  const patched = handle(database, 'PATCH', ['user'], { displayName: '  Ana  ' })
  assert.deepEqual(
    patched.data,
    { login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#e5484d' },
    'PATCH /api/user volta a gravar o nome e mantém o login',
  )
})

test('backfill: coleções existentes e arrays vazios são preservados', () => {
  const legacy = legacyDatabase()
  legacy.experiences = []
  legacy.sources = [{ id: 'meu-projeto', name: 'Meu projeto', path: '/tmp' }]
  legacy.issues = legacy.issues.slice(0, 1)

  const database = load(databaseFile(legacy))

  assert.deepEqual(database.workspaces, legacy.workspaces, 'o workspace existente não é trocado pelo seed')
  assert.deepEqual(database.experiences, [], 'array vazio existente é legítimo e continua vazio')
  assert.deepEqual(database.sources, legacy.sources, 'as sources existentes ficam intactas')
  assert.deepEqual(database.issues, legacy.issues, 'as issues existentes não são duplicadas nem reordenadas')
})

test('sem arquivo, o seed completo continua sendo criado', () => {
  const file = join(root, 'banco-inexistente.json')

  const database = load(file)

  assert.ok(existsSync(file), 'o arquivo é criado quando ainda não existe')
  assert.deepEqual(
    database.user,
    { login: 'admin', displayName: '', avatar: null, avatarColor: '#e5484d' },
    'o banco novo nasce com o seed completo',
  )
})

test('banco vazio, truncado ou não-objeto volta ao seed em vez de derrubar o boot', () => {
  const empty = join(root, 'banco-vazio.json')
  writeFileSync(empty, '')
  const fromEmpty = load(empty)
  assert.equal(fromEmpty.user.login, 'admin', 'arquivo vazio é refeito com o seed')
  assert.ok(fromEmpty.issues.length > 0, 'o seed traz issues para a interface abrir')

  const truncated = join(root, 'banco-truncado.json')
  writeFileSync(truncated, '{"issues": [')
  const fromTruncated = load(truncated)
  assert.equal(fromTruncated.user.login, 'admin', 'JSON inválido não derruba o carregamento')
  assert.ok(
    JSON.parse(readFileSync(truncated, 'utf-8')).issues.length > 0,
    'o arquivo corrompido é reescrito com o seed',
  )

  const nonObject = join(root, 'banco-nulo.json')
  writeFileSync(nonObject, 'null')
  assert.equal(load(nonObject).user.login, 'admin', 'JSON válido que não é objeto também é refeito')
})

test('backfill: user já existente preserva o displayName definido', () => {
  const legacy = legacyDatabase()
  legacy.user = { login: 'admin', displayName: 'Ana' }

  const database = load(databaseFile(legacy))

  assert.deepEqual(
    database.user,
    { login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#e5484d' },
    'a chave presente não é sobrescrita pelo seed; só os campos novos são completados',
  )
})
