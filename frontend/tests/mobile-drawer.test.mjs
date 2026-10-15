import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.css'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

test('drawer mobile CSS and media query breakpoint', () => {
  assert.match(css, /@media\s*\(\s*max-width\s*:\s*1024px\s*\)/, 'deve existir media query para max-width 1024px')
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

test('drawer closing on route navigation and accessibility (Red Phase)', () => {
  // These checks assert behaviors required by the RFC/design that are pending implementation in the Red phase.
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
