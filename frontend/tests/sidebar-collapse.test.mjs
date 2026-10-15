import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.css'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

test('sidebar collapse CSS and classes', () => {
  // Check that the CSS for collapsed state exists
  assert.match(css, /\.sidebar\.collapsed/, 'a classe .sidebar.collapsed deve estar definida')
  assert.match(css, /--activity-width:\s*48px/, 'deve ter variável --activity-width com valor 48px')
  
  // Check that width transition is properly defined
  assert.match(css, /width.*transition/i, 'deve haver transição na largura')
})

test('sidebar toggle button in App.vue (Red Phase)', () => {
  // These checks assert behaviors required by the RFC/design that are pending implementation in the Red phase.
  
  // Check for the sidebar toggle button in App.vue
  assert.match(
    appVue,
    /\.sidebar-toggle/,
    'o botão de toggle do sidebar deve estar presente em App.vue'
  )
  
  // Check for aria attributes
  assert.match(
    appVue,
    /aria-label="Toggle sidebar"/,
    'o botão deve ter aria-label="Toggle sidebar"'
  )
  
  assert.match(
    appVue,
    /aria-expanded/,
    'o botão deve ter o atributo aria-expanded'
  )
  
  // Check for the keyboard shortcut
  assert.match(
    appVue,
    /Ctrl\+B|ctrl\+b/i,
    'deve haver atalho de teclado Ctrl+B'
  )
})

test('sidebar collapse functionality in AppSidebar.vue (Red Phase)', () => {
  // These checks assert behaviors required by the RFC/design that are pending implementation in the Red phase.
  
  // Check for the collapsed prop in AppSidebar.vue
  assert.match(
    sidebarVue,
    /collapsed.*boolean/,
    'AppSidebar deve aceitar a prop collapsed como boolean'
  )
  
  // Check for proper class binding
  assert.match(
    sidebarVue,
    /:class.*collapsed/,
    'deve haver binding condicional de classe para collapsed'
  )
  
  // Check for tooltips (title attributes)
  assert.match(
    sidebarVue,
    /:title/,
    'deve haver title attributes nos elementos de navegação para tooltips'
  )
})

test('mobile behavior preserved (Red Phase)', () => {
  // These checks ensure that mobile behavior is not affected by sidebar collapse functionality
  assert.match(
    css,
    /@media\s*\(\s*max-width\s*:\s*1024px\s*\)/,
    'deve existir media query para max-width 1024px'
  )
  
  // Verify that toggle button is not visible on mobile
  assert.match(
    css,
    /\.sidebar-toggle.*display:\s*none/,
    'botão toggle não deve ser visível em telas menores que 1025px'
  )
})