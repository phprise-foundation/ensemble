import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

import { createSeed } from '../src/data/seed.js'

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
