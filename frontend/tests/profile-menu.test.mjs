import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

import { createSeed } from '../src/data/seed.js'

import { handle } from '../plugin/fake-backend.js'
const here = dirname(fileURLToPath(import.meta.url))
const frontend = join(here, '..')

function source(relativePath) {
  const full = join(frontend, relativePath)
  return existsSync(full) ? readFileSync(full, 'utf8') : ''
}

test('protótipo: usuário corrente singleton e contrato de /api/user', async () => {
  const backend = await import('../plugin/fake-backend.js')
  assert.equal(typeof backend.handle, 'function', 'fake-backend deve exportar handle para o teste de integração')

  const db = createSeed()
  assert.deepEqual(
    db.user,
    { login: 'admin', displayName: '', avatar: null, avatarColor: '#e5484d' },
    'o seed deve trazer o usuário corrente singleton com avatar e cor',
  )

  const fetched = backend.handle(db, 'GET', ['user'], {})
  assert.deepEqual(
    fetched.data,
    { login: 'admin', displayName: '', avatar: null, avatarColor: '#e5484d' },
    'GET /api/user devolve login, displayName, avatar e avatarColor',
  )

  const saved = backend.handle(db, 'PATCH', ['user'], { displayName: '  Maria Fernanda de Souza Oliveira  ' })
  assert.deepEqual(
    saved.data,
    { login: 'admin', displayName: 'Maria Fernanda de Souza Oliveira', avatar: null, avatarColor: '#e5484d' },
    'PATCH /api/user normaliza com trim e aceita até 40 caracteres',
  )

  const refused = backend.handle(db, 'PATCH', ['user'], { displayName: 'x'.repeat(41) })
  assert.equal(refused.status, 422, 'PATCH /api/user recusa acima de 40 caracteres')
  assert.equal(refused.error, 'display name must be at most 40 characters', 'a recusa traz mensagem clara em inglês')
  assert.equal(db.user.displayName, 'Maria Fernanda de Souza Oliveira', 'a recusa preserva o nome anterior')

  const ignored = backend.handle(db, 'PATCH', ['user'], { displayName: 'Ana', login: 'root' })
  assert.equal(ignored.data.login, 'admin', 'PATCH /api/user ignora o login e nunca altera a credencial')
})

test('store de sessão: label cai para o login, initial e signOut/signIn', async () => {
  let module
  try {
    module = await import('../src/store/session.js')
  } catch {
    module = null
  }
  assert.ok(module, 'o store de sessão deve existir em src/store/session.js')

  const session = module.session ?? module.default
  assert.ok(session, 'o store de sessão deve expor o estado reativo')

  session.login = 'admin'
  session.displayName = 'Maria Fernanda de Souza Oliveira'
  assert.equal(session.label, 'Maria Fernanda de Souza Oliveira', 'com nome, o rótulo é o nome de exibição')

  session.displayName = ''
  assert.equal(session.label, 'admin', 'sem nome, o rótulo cai para o identificador de login')
  assert.equal(session.initial, 'A', 'initial é a primeira letra do rótulo em maiúscula')

  session.signOut()
  assert.equal(session.signedIn, false, 'signOut encerra a sessão')
  session.signIn()
  assert.equal(session.signedIn, true, 'signIn restaura a sessão de demonstração do protótipo')
})

test('bindings de API do usuário', () => {
  const api = source('src/api/index.js')
  assert.match(api, /fetchUser:\s*\(\)\s*=>\s*request\('GET',\s*'\/api\/user'\)/, 'api.fetchUser deve ler GET /api/user')
  assert.match(
    api,
    /updateUser:\s*\(data\)\s*=>\s*request\('PATCH',\s*'\/api\/user',\s*data\)/,
    'api.updateUser deve enviar PATCH /api/user',
  )
})

test('ProfileMenu: avatar de 38px, nome abaixo e dropdown profile/logout', () => {
  const menu = source('src/components/ProfileMenu.vue')
  const css = source('src/styles/main.scss')

  assert.match(menu, /class="[^"]*profile-trigger/, 'o acionador .profile-trigger deve existir')
  assert.match(menu, /aria-haspopup="menu"/, 'o acionador anuncia um menu')
  assert.match(menu, /:aria-expanded="open"/, 'o acionador expõe o estado aberto/fechado')
  assert.match(menu, /class="[^"]*profile-avatar/, 'o avatar .profile-avatar deve existir')
  assert.match(menu, /class="[^"]*profile-name/, 'o nome .profile-name deve existir')
  assert.match(menu, /role="menu"/, 'o dropdown deve ser um menu')
  assert.match(menu, /router\.push\('\/profile'\)/, 'o item profile navega para /profile')
  assert.match(menu, /signOut/, 'o item logout encerra a sessão')

  assert.match(
    menu,
    /<Avatar[\s\S]*?class="profile-avatar"[\s\S]*?:image="session\.avatar"[\s\S]*?:color="session\.avatarColor"[\s\S]*?\/>/,
    'o Avatar recebe a foto e a cor do usuário',
  )
  assert.doesNotMatch(menu, /session\.initial/, 'a inicial não é desenhada à mão: o fallback é do Avatar')

  const avatarCss = css.match(/\.avatar\s*\{[^}]*\}/)?.[0] || ''
  assert.match(avatarCss, /border-radius:\s*50%/, 'o Avatar compartilhado é circular')
  assert.match(css, /\.profile-avatar[\s\S]*?width:\s*38px/, 'o avatar deve ter 38px')
  assert.match(css, /\.profile-avatar[\s\S]*?height:\s*38px/, 'o avatar deve ter 38px de altura')
})

test('PATCH /api/user parcial: campo ausente preserva o valor atual', () => {
  const db = createSeed()
  const photo = 'data:image/webp;base64,AAAA'
  handle(db, 'PATCH', ['user'], { displayName: 'Ana', avatar: photo, avatarColor: '#00b8d9' })

  const renamed = handle(db, 'PATCH', ['user'], { displayName: 'Bruna' })
  assert.deepEqual(
    renamed.data,
    { login: 'admin', displayName: 'Bruna', avatar: photo, avatarColor: '#00b8d9' },
    'o PATCH de só o nome preserva foto e cor',
  )

  const recolored = handle(db, 'PATCH', ['user'], { avatarColor: '#46a758' })
  assert.deepEqual(
    recolored.data,
    { login: 'admin', displayName: 'Bruna', avatar: photo, avatarColor: '#46a758' },
    'o PATCH de só a cor preserva nome e foto',
  )

  const unmutated = createSeed()
  const photoOnly = handle(unmutated, 'PATCH', ['user'], { avatar: photo })
  assert.deepEqual(
    photoOnly.data,
    { login: 'admin', displayName: '', avatar: photo, avatarColor: '#e5484d' },
    'definir a foto não inventa nem altera nome e cor',
  )
})

test('PATCH /api/user com avatar null remove a foto e repetir o PATCH é idempotente', () => {
  const db = createSeed()
  handle(db, 'PATCH', ['user'], { avatar: 'data:image/png;base64,BBBB' })

  const removed = handle(db, 'PATCH', ['user'], { avatar: null })
  assert.equal(removed.data.avatar, null, 'avatar null remove a foto')
  assert.equal(removed.data.displayName, '', 'remover a foto preserva o nome')
  assert.equal(removed.data.avatarColor, '#e5484d', 'remover a foto preserva a cor')

  const repeated = handle(db, 'PATCH', ['user'], { avatar: null })
  assert.deepEqual(repeated.data, removed.data, 'repetir o mesmo PATCH produz o mesmo estado')

  const read = handle(db, 'GET', ['user'], {})
  assert.deepEqual(read.data, removed.data, 'o GET confirma o estado final único')
})

test('PATCH /api/user valida tudo antes de mutar: 422 não altera nem o campo válido', () => {
  const db = createSeed()
  handle(db, 'PATCH', ['user'], { displayName: 'Ana' })

  const refused = handle(db, 'PATCH', ['user'], { displayName: 'Bruna', avatar: 'http://exemplo/foto.png' })
  assert.equal(refused.status, 422, 'avatar fora do contrato recusa o PATCH inteiro')
  assert.equal(refused.field, 'avatar', 'a recusa aponta o campo inválido')
  assert.equal(db.user.displayName, 'Ana', 'o displayName válido do corpo não é aplicado')
  assert.equal(db.user.avatar, null, 'nenhum campo é aplicado num PATCH recusado')

  const recolored = handle(db, 'PATCH', ['user'], { avatarColor: 'azul' })
  assert.equal(recolored.status, 422, 'avatarColor não hex recusa com 422')
  assert.equal(db.user.avatarColor, '#e5484d', 'a cor anterior permanece')
})

test('PATCH /api/user recusa avatar fora do contrato com 422', () => {
  const cases = [
    'http://exemplo/foto.png',
    'data:text/html;base64,AAAA',
    'data:image/gif;base64,AAAA',
    'data:image/webp;base64',
    42,
    'data:image/png;base64,####',
  ]
  for (const avatar of cases) {
    const db = createSeed()
    const refused = handle(db, 'PATCH', ['user'], { avatar })
    assert.equal(refused.status, 422, `avatar ${JSON.stringify(avatar)} é recusado com 422`)
    assert.equal(refused.field, 'avatar', 'a recusa aponta o campo avatar')
  }
})

test('PATCH /api/user recusa avatar acima de 512 KiB com 422', () => {
  const db = createSeed()
  const oversized = 'data:image/png;base64,' + 'A'.repeat(512 * 1024 + 1)
  const refused = handle(db, 'PATCH', ['user'], { avatar: oversized })
  assert.equal(refused.status, 422, 'base64 acima do teto é recusado')
  assert.equal(db.user.avatar, null, 'o estado permanece sem foto')

  const exactlyAtLimit = 'data:image/png;base64,' + 'A'.repeat(512 * 1024)
  const accepted = handle(db, 'PATCH', ['user'], { avatar: exactlyAtLimit })
  assert.equal(accepted.status, undefined, 'exatamente no teto é aceito')
  assert.equal(accepted.data.avatar, exactlyAtLimit, 'a foto no teto é definida')
})

test('PATCH /api/user recusa avatarColor inválido e displayName fora do contrato', () => {
  for (const avatarColor of ['azul', '#00b8d', '#00b8d99', '00b8d9', 42, null]) {
    const db = createSeed()
    const refused = handle(db, 'PATCH', ['user'], { avatarColor })
    assert.equal(refused.status, 422, `avatarColor ${JSON.stringify(avatarColor)} é recusado com 422`)
    assert.equal(refused.field, 'avatarColor', 'a recusa aponta o campo avatarColor')
  }

  const db = createSeed()
  const nonString = handle(db, 'PATCH', ['user'], { displayName: 42 })
  assert.equal(nonString.status, 422, 'displayName não string é recusado com 422')
  assert.equal(db.user.displayName, '', 'o nome anterior permanece')
})

test('PATCH /api/user nunca altera o login e o GET expõe exatamente quatro campos', () => {
  const db = createSeed()
  const patched = handle(db, 'PATCH', ['user'], { displayName: 'Ana', login: 'root', avatarColor: '#00b8d9' })
  assert.equal(patched.data.login, 'admin', 'o login é ignorado no PATCH')

  const read = handle(db, 'GET', ['user'], {})
  assert.deepEqual(
    Object.keys(read.data).sort(),
    ['avatar', 'avatarColor', 'displayName', 'login'],
    'o contrato expõe login, displayName, avatar e avatarColor',
  )
})
