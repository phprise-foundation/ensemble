import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')
const breakpoints = readFileSync(join(here, '..', 'src', 'breakpoints.js'), 'utf8')

test('drawer mobile CSS and media query breakpoint', () => {
  assert.match(css, /\$sidebar-breakpoint:\s*1024px;/, 'deve existir uma variavel SCSS unica com o breakpoint')
  assert.match(css, /@media\s*\(\s*max-width\s*:\s*\$sidebar-breakpoint\s*\)/, 'a media query deve consumir a variavel SCSS')
  assert.doesNotMatch(css, /@media\s*\(\s*max-width\s*:\s*1024px\s*\)/, 'nao deve haver media query literal com 1024px')
  assert.match(css, /\.sidebar/, 'a classe .sidebar deve estar definida')
  assert.match(css, /\.scrim/, 'a classe .scrim deve estar definida para cobrir a tela ao abrir o drawer')
})

test('base drawer UI and interaction logic', () => {
  const desktopSidebarRule = css.match(/\.sidebar\s*\{[^}]*\}/s)?.[0] || ''

  assert.match(appVue, /const sidebarOpen = ref\(false\)/, 'o drawer deve começar recolhido')
  assert.match(appVue, /sidebarOpen = true/, 'o botão toggle deve abrir o drawer')
  assert.match(appVue, /@click="sidebarOpen = false"/, 'clique no scrim deve fechar o drawer')
  assert.match(appVue, /:open="sidebarOpen"/, 'AppSidebar deve receber o estado aberto/fechado')
  assert.match(appVue, /@close="sidebarOpen = false"/, 'AppSidebar deve emitir evento para fechar')
  assert.match(sidebarVue, /id="app-sidebar"/, 'o menu desktop deve ser o único conteúdo do drawer')
  assert.doesNotMatch(desktopSidebarRule, /position:\s*fixed/, 'sidebar desktop não deve ser fixed')
})

test('drawer closing on route navigation and accessibility', () => {
  assert.match(
    sidebarVue,
    /router\.afterEach|watch\(.*route/i,
    'o drawer deve fechar automaticamente ao trocar de rota'
  )
  assert.match(
    sidebarVue,
    /keydown.*escape|focus\s*trap/i,
    'o drawer deve implementar acessibilidade (tecla ESC e foco preso)'
  )
})

test('drawer closes when opening the editor on mobile', () => {
  assert.match(
    sidebarVue,
    /function open\([^)]*\)\s*\{[\s\S]*?emit\('close'\)/,
    'abrir o editor deve emitir close para recolher o drawer'
  )
  assert.match(
    sidebarVue,
    /<Teleport\s+to="body">[\s\S]*?(<EntityModal[\s\S]*?\/>|<BoardEditor[\s\S]*?\/>)/,
    'os editores devem ser teleportados para fora do aside sidebar'
  )
})

test('mobile breakpoint has a single address consumed by CSS, tests and JavaScript', () => {
  assert.match(css, /--breakpoint-sidebar:\s*#\{\$sidebar-breakpoint\}/, 'o corte deve ser publicado como propriedade CSS custom')
  assert.match(breakpoints, /--breakpoint-sidebar/, 'o JavaScript deve ler o corte da propriedade CSS custom')
  assert.doesNotMatch(breakpoints, /1024/, 'o JavaScript não deve copiar o valor do corte')
  assert.doesNotMatch(appVue, /1024/, 'App.vue não deve copiar o valor do corte')
  assert.doesNotMatch(sidebarVue, /1024/, 'AppSidebar.vue não deve copiar o valor do corte')
})

test('modal semantics apply only to the mobile overlay, preserving desktop navigation', () => {
  assert.match(sidebarVue, /overlay:\s*\{\s*type:\s*Boolean/, 'AppSidebar deve receber o modo overlay por prop')
  assert.ok(sidebarVue.includes(`:role="isModal ? 'dialog' : undefined"`), 'o role de diálogo deve ser condicional ao overlay')
  assert.ok(sidebarVue.includes(`:aria-modal="isModal ? 'true' : undefined"`), 'aria-modal deve ser condicional ao overlay')
  assert.ok(sidebarVue.includes(`:aria-hidden="overlay ? !open : undefined"`), 'aria-hidden deve existir apenas no overlay')
  assert.doesNotMatch(sidebarVue, /role="dialog"/, 'não deve haver role de diálogo incondicional')
})

test('opening the mobile overlay moves focus inside the drawer', () => {
  assert.match(sidebarVue, /nextTick\(focusFirst\)/, 'o foco deve entrar no drawer ao abrir no modo overlay')
  assert.ok(appVue.includes(`:inert="sidebarOpen && mobile ? true : null"`), 'o conteúdo de fundo deve ficar inert no overlay aberto')
})
