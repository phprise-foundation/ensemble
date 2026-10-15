import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.css'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

test('sidebar collapse CSS and media query breakpoint', () => {
  // Check for sidebar width CSS and collapse behavior
  assert.match(css, /--sidebar-width:\s*200px/, 'deve ter variável de largura padrão de 200px')
  assert.match(css, /--activity-width:\s*48px/, 'deve ter variável de largura atividade de 48px')
  assert.match(css, /\.sidebar.*width.*var\(\s*--sidebar-width\s*\)/, 'sidebar deve usar a variável de largura')
  assert.match(css, /transition.*width.*ease/, 'sidebar deve ter transição na largura')
  // Check for collapsed class and behavior
  assert.match(css, /\.sidebar\.collapsed/, 'classe .collapsed deve estar definida')
})

test('sidebar collapse functionality (Red Phase)', () => {
  // These checks assert behaviors required by the RFC/design that are pending implementation in the Red phase.
  
  // Check that sidebar props have correct definition for collapsed state
  assert.match(
    sidebarVue,
    /collapsed:\s*\{\s*type:\s*Boolean/,
    'prop collapsed deve ter tipo Boolean (maiúsculo)'
  )
  
  // Check if CSS has proper transition declaration order - transition must come before width
  assert.match(
    css,
    /transition:[^;]*width/,
    'CSS deve declarar transition antes de width'
  )
  
  // Check for sidebar toggle toggle button
  assert.match(
    appVue,
    /\.sidebar-toggle/,
    'botão .sidebar-toggle deve estar presente'
  )
  
  // Check for CSS for mobile behavior - need to match across newlines
  assert.match(
    css,
    /@media\s*\(\s*max-width\s*:\s*1024px\s*\)[\s\S]*\.sidebar-toggle\s*{[\s\S]*display:\s*none/,
    'no modo mobile, o botão sidebar-toggle deve ser oculto'
  )
})

test('sidebar toggle button in App.vue', () => {
  // Verify the sidebar toggle button exists in App.vue
  assert.match(
    appVue,
    /class="sidebar-toggle"/,
    'o componente App.vue deve ter o botão .sidebar-toggle'
  )
})

test('sidebar collapse logic in AppSidebar.vue', () => {
  // Verify the sidebar collapse functionality 
  assert.match(
    sidebarVue,
    /class="sidebar.*collapsed/,
    'o sidebar deve aplicar a classe collapsed quando colapsado'
  )
})