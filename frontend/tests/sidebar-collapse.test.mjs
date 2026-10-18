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

test('sidebar toggle button in App.vue', () => {
  assert.match(appVue, /class="[^"]*sidebar-toggle/, 'o botao de toggle do sidebar deve estar presente em App.vue')
  assert.match(appVue, /aria-label="Toggle sidebar"/, 'o botao deve ter aria-label="Toggle sidebar"')
  assert.match(appVue, /aria-expanded/, 'o botao deve ter o atributo aria-expanded')
  assert.match(appVue, /event\.key\s*===?\s*['"]b['"]/, 'deve escutar a tecla b')
  assert.match(appVue, /ctrlKey|metaKey/, 'deve escutar atalho Ctrl ou Meta')
})

test('sidebar collapse functionality in AppSidebar.vue', () => {
  assert.match(sidebarVue, /collapsed[\s\S]*Boolean/, 'AppSidebar deve aceitar a prop collapsed como boolean')
  assert.match(sidebarVue, /:class[\s\S]*collapsed/, 'deve haver binding condicional de classe para collapsed')
  assert.match(sidebarVue, /:title/, 'deve haver title attributes nos elementos de navegacao para tooltips')
})

test('mobile behavior preserved', () => {
  assert.match(css, /@media\s*\(\s*max-width\s*:\s*\$sidebar-breakpoint\s*\)/, 'a media query do drawer mobile deve consumir a variavel SCSS do breakpoint')
  assert.match(css, /\.sidebar-toggle\s*\{[\s\S]*?display:\s*none/, 'botao toggle nao deve ser visivel por padrao em telas menores')
})

test('visible focus indicator for toggle and icon buttons', () => {
  assert.match(css, /\.icon-btn:focus-visible/, 'deve haver indicador de foco visivel para botoes de icone')
})

test('collapsed state hides leaf labels, leaving only icons', () => {
  assert.match(css, /\.sidebar\.collapsed\s+\.leaf\s+span/, 'os rotulos das folhas devem ser ocultados no estado colapsado')
})

test('collapse state is deterministic and not persisted in Fase 1', () => {
  assert.match(appVue, /const\s+sidebarCollapsed\s*=\s*ref\s*\(\s*false\s*\)/, 'estado padrao ao carregar eh expandido')
  assert.doesNotMatch(appVue, /localStorage/, 'Fase 1 nao persiste preferencia no localStorage')
  assert.match(appVue, /sidebarCollapsed\s*=\s*!sidebarCollapsed/, 'clique do toggle alterna o estado')
  assert.match(appVue, /sidebarCollapsed\.value\s*=\s*!sidebarCollapsed\.value/, 'atalho de teclado alterna o mesmo estado')
})

test('desktop toggle breakpoint shares the single source of truth with KAN-9', () => {
  assert.match(css, /@media\s*\(\s*min-width\s*:\s*\$sidebar-breakpoint\s*\+\s*1\s*\)/, 'a media query desktop do toggle consome a variavel SCSS do breakpoint')
})

const DESKTOP_MEDIA = /@media\s*\(\s*min-width\s*:\s*\$sidebar-breakpoint\s*\+\s*1\s*\)\s*\{([\s\S]*?)\n\}/g

function desktopCss(source) {
  return [...source.matchAll(DESKTOP_MEDIA)].map((match) => match[1]).join('\n')
}

function outsideDesktopCss(source) {
  return source.replace(DESKTOP_MEDIA, '')
}

test('regras do colapso ficam escopadas ao desktop', () => {
  assert.match(desktopCss(css), /\.sidebar\.collapsed/, 'as regras de .sidebar.collapsed devem viver na media query de desktop')
  assert.doesNotMatch(outsideDesktopCss(css), /\.sidebar\.collapsed/, 'nenhuma regra de .sidebar.collapsed pode valer fora do desktop')
})

test('atalho de colapso inerte no mobile', () => {
  const handler = appVue.match(/function\s+handleKeyboardShortcut\s*\(event\)\s*\{([\s\S]*?)\n\}/)
  assert.ok(handler, 'o atalho de teclado deve existir em App.vue')
  assert.match(handler[1], /isMobile\.value/, 'o atalho deve consultar o viewport antes de alternar o estado')
})
