import { mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { handle, load } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

const root = mkdtempSync(join(tmpdir(), 'kan-129-'))

after(() => {
  rmSync(root, { recursive: true, force: true })
})

let fileCount = 0

function databaseWithUser(user) {
  const database = createSeed()
  database.user = user
  const file = join(root, `db-${fileCount}.json`)
  fileCount += 1
  writeFileSync(file, JSON.stringify(database))
  return load(file)
}

function freshDatabase() {
  return databaseWithUser({ login: 'admin', displayName: '' })
}

const photo = 'data:image/webp;base64,UklGRg=='

test('PATCH /api/user é parcial: campo ausente preserva o valor atual', () => {
  const database = databaseWithUser({ login: 'admin', displayName: 'Ana', avatar: photo, avatarColor: '#4c9aff' })

  const onlyName = handle(database, 'PATCH', ['user'], { displayName: 'Bia' })
  assert.deepEqual(onlyName.data, { login: 'admin', displayName: 'Bia', avatar: photo, avatarColor: '#4c9aff' })

  const onlyColor = handle(database, 'PATCH', ['user'], { avatarColor: '#00b8d9' })
  assert.deepEqual(onlyColor.data, { login: 'admin', displayName: 'Bia', avatar: photo, avatarColor: '#00b8d9' })

  const empty = handle(database, 'PATCH', ['user'], {})
  assert.deepEqual(empty.data, { login: 'admin', displayName: 'Bia', avatar: photo, avatarColor: '#00b8d9' })
})

test('PATCH /api/user remove a foto com avatar null e aceita as três mídias', () => {
  const database = databaseWithUser({ login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#4c9aff' })

  for (const type of ['png', 'jpeg', 'webp']) {
    const saved = handle(database, 'PATCH', ['user'], { avatar: `data:image/${type};base64,UklGRg==` })
    assert.equal(saved.status, undefined, `data URL ${type} é aceita`)
    assert.equal(saved.data.avatar, `data:image/${type};base64,UklGRg==`)
  }

  const removed = handle(database, 'PATCH', ['user'], { avatar: null })
  assert.equal(removed.data.avatar, null, 'avatar null remove a foto')
  assert.equal(database.user.avatar, null, 'a remoção é persistida')
})

test('PATCH /api/user repete o mesmo corpo sem mudar o estado', () => {
  const database = freshDatabase()
  const body = { displayName: '  Ana  ', avatar: photo, avatarColor: '#8777d9' }

  const first = handle(database, 'PATCH', ['user'], body)
  const second = handle(database, 'PATCH', ['user'], body)

  assert.deepEqual(second, first, 'o PATCH é idempotente')
  assert.deepEqual(handle(database, 'GET', ['user'], {}).data, first.data, 'o GET reflete o mesmo estado')
})

test('PATCH /api/user recusa avatar fora do contrato com 422', () => {
  const invalids = [
    'data:image/gif;base64,UklGRg==',
    'data:image/png,UklGRg==',
    'data:image/png;base64,',
    'data:image/png;base64,-----',
    'data:image/png;base64,Ukl GRg==',
    'https://exemplo.test/foto.png',
    'Qualquer texto',
    42,
    true,
  ]

  for (const avatar of invalids) {
    const database = freshDatabase()
    const refused = handle(database, 'PATCH', ['user'], { avatar })
    assert.equal(refused.status, 422, `recusa ${JSON.stringify(avatar)}`)
    assert.equal(refused.field, 'avatar')
    assert.equal(database.user.avatar, null, 'a recusa não muta o avatar')
  }
})

test('PATCH /api/user recusa avatar acima de 512 KiB em base64', () => {
  const accepted = handle(freshDatabase(), 'PATCH', ['user'], { avatar: `data:image/png;base64,${'A'.repeat(512 * 1024)}` })
  assert.notEqual(accepted.status, 422, 'o limite exato ainda é aceito')

  const refused = handle(freshDatabase(), 'PATCH', ['user'], { avatar: `data:image/png;base64,${'A'.repeat(512 * 1024 + 4)}` })
  assert.equal(refused.status, 422)
  assert.equal(refused.field, 'avatar')
})

test('PATCH /api/user recusa avatarColor diferente de hex de seis dígitos', () => {
  for (const avatarColor of ['#fff', 'red', '4c9aff', '#4c9af', '#4c9afff', '#4c9afg', null, 12]) {
    const database = freshDatabase()
    const refused = handle(database, 'PATCH', ['user'], { avatarColor })
    assert.equal(refused.status, 422, `recusa ${JSON.stringify(avatarColor)}`)
    assert.equal(refused.field, 'avatarColor')
    assert.equal(database.user.avatarColor, '#e5484d', 'a recusa não muta a cor')
  }

  assert.equal(handle(freshDatabase(), 'PATCH', ['user'], { avatarColor: '#4C9AFF' }).data.avatarColor, '#4C9AFF')
})

test('PATCH /api/user válida tudo antes de qualquer mutação', () => {
  const database = databaseWithUser({ login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#4c9aff' })

  const refused = handle(database, 'PATCH', ['user'], { displayName: 'Bia', avatar: photo, avatarColor: 'verde' })

  assert.equal(refused.status, 422)
  assert.equal(refused.field, 'avatarColor')
  assert.deepEqual(database.user, { login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#4c9aff' })
})

test('PATCH /api/user mantém o contrato do displayName', () => {
  const database = freshDatabase()

  const tooLong = handle(database, 'PATCH', ['user'], { displayName: 'x'.repeat(41), avatar: photo })
  assert.equal(tooLong.status, 422)
  assert.equal(tooLong.field, 'displayName')
  assert.equal(database.user.avatar, null, 'o nome inválido não deixa a foto entrar')

  const notString = handle(database, 'PATCH', ['user'], { displayName: null })
  assert.equal(notString.status, 422)

  const limit = handle(database, 'PATCH', ['user'], { displayName: `  ${'y'.repeat(40)}  ` })
  assert.equal(limit.data.displayName, 'y'.repeat(40), 'trim antes do limite de 40')
})

test('boot completa avatar e avatarColor ausentes sem tocar foto salva', () => {
  const legacy = createSeed()
  legacy.user = { login: 'admin', displayName: 'Ana' }
  const legacyFile = join(root, 'user-legado.json')
  writeFileSync(legacyFile, JSON.stringify(legacy))

  assert.deepEqual(
    load(legacyFile).user,
    { login: 'admin', displayName: 'Ana', avatar: null, avatarColor: '#e5484d' },
    'boot completa os campos novos sem sobrescrever o nome',
  )

  const saved = databaseWithUser({ login: 'admin', displayName: 'Ana', avatar: photo, avatarColor: '#4c9aff' })
  assert.deepEqual(saved.user, { login: 'admin', displayName: 'Ana', avatar: photo, avatarColor: '#4c9aff' })
})
