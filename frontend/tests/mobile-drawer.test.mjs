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
