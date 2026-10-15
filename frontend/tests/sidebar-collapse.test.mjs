import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

test('sidebar collapse CSS and classes', () => {
  assert.match(css, /\.sidebar\.collapsed/, 'a classe .sidebar.collapsed deve estar definida')
  assert.match(css, /--activity-width:\s*48px/, 'deve ter variavel --activity-width com valor 48px')
  assert.match(css, /transition:[^;]*width/, 'deve haver transicao na largura')
})

test('sidebar toggle button in App.vue (Red Phase)', () => {
  assert.match(appVue, /class="[^"]*sidebar-toggle/, 'o botao de toggle do sidebar deve estar presente em App.vue')
  assert.match(appVue, /aria-label="Toggle sidebar"/, 'o botao deve ter aria-label="Toggle sidebar"')
  assert.match(appVue, /aria-expanded/, 'o botao deve ter o atributo aria-expanded')
  assert.match(appVue, /event\.key\s*===?\s*['"]b['"]/, 'deve escutar a tecla b')
  assert.match(appVue, /ctrlKey|metaKey/, 'deve escutar atalho Ctrl ou Meta')
})

test('sidebar collapse functionality in AppSidebar.vue (Red Phase)', () => {
  assert.match(sidebarVue, /collapsed[\s\S]*Boolean/, 'AppSidebar deve aceitar a prop collapsed como boolean')
  assert.match(sidebarVue, /:class[\s\S]*collapsed/, 'deve haver binding condicional de classe para collapsed')
  assert.match(sidebarVue, /:title/, 'deve haver title attributes nos elementos de navegacao para tooltips')
})

test('mobile behavior preserved (Red Phase)', () => {
  assert.match(css, /@media\s*\(\s*max-width\s*:\s*\$sidebar-breakpoint\s*\)/, 'a media query deve consumir a variavel SCSS do breakpoint')
  assert.match(css, /\.sidebar-toggle\s*\{[\s\S]*?display:\s*none/, 'botao toggle nao deve ser visivel por padrao em telas menores')
})
