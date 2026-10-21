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

  assert.match(css, /\.profile-avatar[\s\S]*?width:\s*38px/, 'o avatar deve ter 38px')
  assert.match(css, /\.profile-avatar[\s\S]*?border-radius:\s*50%/, 'o avatar deve ser circular')
})

test('nome em linha única com reticências e largura máxima fixa', () => {
  const css = source('src/styles/main.scss')
  const nameRule = css.match(/\.profile-name\s*\{[^}]*\}/)?.[0] || ''

  assert.match(nameRule, /white-space:\s*nowrap/, 'o nome não pode quebrar em duas linhas')
  assert.match(nameRule, /text-overflow:\s*ellipsis/, 'o nome deve ser truncado com reticências')
  assert.match(nameRule, /overflow:\s*hidden/, 'o excedente do nome deve ser escondido')
  assert.match(css, /max-width:\s*14ch/, 'no desktop o nome tem largura máxima fixa')
  assert.match(css, /max-width:\s*11ch/, 'em tela pequena o nome tem largura máxima reduzida')
})

test('App.vue monta o menu no topo e fecha a área protegida no fim de sessão', () => {
  const appVue = source('src/App.vue')

  assert.match(appVue, /<ProfileMenu/, 'App.vue deve montar o ProfileMenu no topo')
  assert.match(appVue, /session\.load\(\)/, 'App.vue deve hidratar a sessão no boot')
  assert.match(appVue, /v-if="session\.signedIn"/, 'a casca autenticada deve ser condicionada a session.signedIn')
  assert.match(appVue, /v-else/, 'deve haver uma vista alternativa para a sessão encerrada')
  assert.match(appVue, /session-ended|Session ended/, 'a vista de fim de sessão é neutra e visível')
})

test('tela de profile edita o nome sem tocar no login', () => {
  const route = source('src/router.js')
  const page = source('src/views/ProfilePage.vue')

  assert.match(route, /path:\s*'\/profile'/, "'/profile' deve ser rota própria, distinta de /teams/:teamId/profiles")
  assert.match(route, /ProfilePage/, 'a rota /profile deve apontar para o ProfilePage')
  assert.match(page, /session\.rename/, 'a tela deve chamar session.rename(name)')
  assert.match(page, /maxlength="?40"?/, 'o formulário limita o nome a 40 caracteres')
  assert.match(
    page,
    /watch\(\s*\(\)\s*=>\s*session\.displayName/,
    'o campo do nome acompanha a sessão quando ela hidrata depois da montagem',
  )
  assert.match(page, /readonly/, 'o login fica somente leitura na tela')
})

test('especificação E2E de navegador para o menu de perfil', () => {
  const spec = source('cypress/e2e/profile-menu.cy.js')

  assert.match(spec, /profile-trigger/, 'o E2E abre o menu de perfil na interface')
  assert.match(spec, /Session ended/, 'o E2E cobre o fim de sessão no navegador')
  assert.match(spec, /vite-error-overlay/, 'o E2E falha em erro de runtime/overlay do Vite')
  assert.match(spec, /getBoundingClientRect/, 'o E2E mede o avatar de 38px no navegador (grão 1 da seção 11)')
  assert.match(spec, /--breakpoint-sidebar/, 'o E2E lê o corte da variável CSS da KAN-12, sem literal (grão 2 da seção 11)')
  assert.doesNotMatch(spec, /\b1024\b/, 'o E2E não repete o literal do corte do sidebar')
  assert.match(spec, /role="status"/, 'o E2E confirma o sucesso pela região viva, não pela string')
})
