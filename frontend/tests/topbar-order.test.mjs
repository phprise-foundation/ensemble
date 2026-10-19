import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const frontend = join(here, '..')

const appVue = readFileSync(join(frontend, 'src', 'App.vue'), 'utf8')
const css = readFileSync(join(frontend, 'src', 'styles', 'main.scss'), 'utf8')

const CANONICAL_SLOTS = ['sim', 'grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile']
const GRID_CLASSES = ['sidebar-toggle', 'console-toggle', 'copilot-toggle']

function openingTag(source, marker) {
  const markerIndex = source.indexOf(marker)
  assert.notEqual(markerIndex, -1, `o elemento ${marker} deve existir em App.vue`)
  const start = source.lastIndexOf('<', markerIndex)
  const end = source.indexOf('>', markerIndex)
  return source.slice(start, end + 1)
}

function slotNames() {
  return [...appVue.matchAll(/data-topbar-slot="([^"]+)"/g)].map((match) => match[1])
}

function consoleInTree() {
  return /AppConsole|console-toggle|app-console/.test(appVue)
}

function expectedSlots() {
  return consoleInTree()
    ? CANONICAL_SLOTS
    : CANONICAL_SLOTS.filter((slot) => slot !== 'grid-bottom')
}

test('grupo direito único depois do spacer, dentro do header', () => {
  const headerIndex = appVue.indexOf('<header class="topbar">')
  const spacerIndex = appVue.indexOf('<div class="spacer">')
  const actionsIndex = appVue.indexOf('<div class="topbar-actions">')
  const headerEnd = appVue.indexOf('</header>', actionsIndex)

  assert.notEqual(headerIndex, -1, 'o header do topbar deve existir')
  assert.notEqual(spacerIndex, -1, 'o spacer deve existir')
  assert.notEqual(actionsIndex, -1, 'deve existir um único container .topbar-actions')
  assert.ok(actionsIndex > spacerIndex, '.topbar-actions deve vir depois do spacer')
  assert.ok(headerEnd > actionsIndex, '.topbar-actions deve viver dentro do header')
})

test('slots na ordem canônica, sem slot desconhecido ou duplicado', () => {
  const slots = slotNames()

  assert.ok(slots.length > 0, 'os slots data-topbar-slot devem estar no template')
  assert.deepEqual(slots, expectedSlots(), 'a ordem DOM deve ser a canônica da fase vigente')
  assert.equal(new Set(slots).size, slots.length, 'nenhum slot pode aparecer duas vezes')
  assert.equal(slots[0], 'sim', 'Sim é o primeiro slot do grupo direito')
  assert.equal(slots.at(-1), 'profile', 'perfil é o último slot do grupo direito')
})

test('a ordem esperada acompanha a presença estrutural do console', () => {
  if (consoleInTree()) {
    assert.ok(slotNames().includes('grid-bottom'), 'com console na árvore, grid-bottom deve existir')
    return
  }

  assert.ok(!slotNames().includes('grid-bottom'), 'sem console na árvore, grid-bottom não pode existir')
})

test('nenhum controle de grade fica fora do grupo direito', () => {
  const headerIndex = appVue.indexOf('<header class="topbar">')
  const actionsIndex = appVue.indexOf('<div class="topbar-actions">')
  const beforeActions = appVue.slice(headerIndex, actionsIndex)
  const actionsBlock = appVue.slice(actionsIndex, appVue.indexOf('</header>', actionsIndex))

  for (const klass of GRID_CLASSES) {
    assert.doesNotMatch(beforeActions, new RegExp(klass), `${klass} não pode ficar à esquerda do grupo direito`)
  }

  for (const klass of GRID_CLASSES.filter((name) => name !== 'console-toggle')) {
    assert.match(actionsBlock, new RegExp(klass), `${klass} deve viver em .topbar-actions`)
  }

  if (consoleInTree()) {
    assert.match(actionsBlock, /console-toggle/, 'console-toggle deve viver em .topbar-actions')
  }

  assert.doesNotMatch(beforeActions, /sim-toggle/, 'Sim não pode ficar à esquerda do grupo direito')
  assert.match(beforeActions, /menu-toggle/, 'o menu mobile continua à esquerda do título')
  assert.ok(
    beforeActions.indexOf('<h1') > beforeActions.indexOf('menu-toggle'),
    'o título vem depois do menu mobile',
  )
})

test('Sim é botão de alternância com rótulo e aria-pressed', () => {
  const sim = openingTag(appVue, 'data-topbar-slot="sim"')

  assert.match(sim, /^<button/, 'Sim deve ser um <button>, não um rótulo inerte')
  assert.match(sim, /class="[^"]*sim-toggle/, 'Sim deve ter a classe .sim-toggle')
  assert.match(sim, /aria-pressed="hasSimulation"/, 'aria-pressed deve refletir hasSimulation')
  assert.match(sim, /aria-label="[^"]*Sim/, 'Sim deve ter aria-label com o nome visível')
  assert.match(sim, /:title=/, 'Sim deve ter título dinâmico')
  assert.match(appVue, /<span class="sim-dot[^"]*"><\/span>\s*Sim/, 'Sim mantém dot e rótulo visível')
  assert.match(appVue, /setAll\(!hasSimulation\.value\)/, 'clique do Sim deve alternar a simulação')
})

test('grades desktop usam v-if e ARIA ligada ao painel', () => {
  const sidebar = openingTag(appVue, 'data-topbar-slot="grid-left"')
  assert.match(sidebar, /v-if="!isMobile"/, 'grid-left deve usar v-if="!isMobile"')
  assert.match(sidebar, /aria-controls="app-sidebar"/, 'grid-left deve controlar o sidebar')
  assert.match(sidebar, /aria-expanded="!sidebarCollapsed"/, 'aria-expanded deve refletir o colapso')

  const copilot = openingTag(appVue, 'data-topbar-slot="grid-right"')
  assert.match(copilot, /v-if="!isMobile"/, 'grid-right deve usar v-if="!isMobile"')
  assert.match(copilot, /aria-controls="app-copilot"/, 'grid-right deve controlar o copilot')
  assert.match(copilot, /:aria-expanded="copilotOpen"/, 'aria-expanded deve refletir o painel')

  assert.match(appVue, /data-topbar-slot="profile"/, 'ProfileMenu deve receber o slot profile')
})

test('tema tem nome acessível e é o último controle antes do perfil', () => {
  const themeButton = openingTag(appVue, 'data-topbar-slot="theme"')

  assert.match(themeButton, /^<button/, 'tema deve ser um botão')
  assert.match(themeButton, /aria-label="[^"]*theme/i, 'tema deve ter aria-label')
  assert.match(themeButton, /:title="theme\.mode/, 'tema deve ter title dinâmico')
  assert.match(themeButton, /toggleTheme/, 'o botão de tema deve alternar o tema')
})

test('CSS do grupo direito não encolhe e o título trunca', () => {
  assert.match(css, /\.topbar-actions\s*\{[^}]*display:\s*flex/, '.topbar-actions deve ser flex')
  assert.match(css, /\.topbar-actions\s*\{[^}]*flex:\s*0 0 auto/, '.topbar-actions não pode encolher')
  assert.match(css, /\.topbar-actions\s*\{[^}]*gap:\s*8px/, '.topbar-actions deve ter gap de 8px')
  assert.match(css, /\.topbar h1\s*\{[^}]*min-width:\s*0/, 'o título deve poder encolher')
  assert.match(css, /\.topbar h1\s*\{[^}]*flex:\s*0 1 auto/, 'o título deve ter flex 0 1 auto')
  assert.match(css, /\.topbar h1\s*\{[^}]*text-overflow:\s*ellipsis/, 'o título deve truncar')
  assert.match(css, /\.topbar \.sim-toggle\s*\{[^}]*font-size:\s*12px/, '.sim-toggle deve ser compacto')
  assert.match(css, /\.topbar-actions button:focus-visible\s*\{[^}]*outline:\s*2px/, 'controles devem ter foco visível')
})

test('breakpoint único: nada de segundo corte numérico', () => {
  const withoutToken = css.replace(/\$sidebar-breakpoint:\s*1024px;/, '')

  assert.match(css, /--breakpoint-sidebar:\s*#\{\$sidebar-breakpoint\}/, 'o corte deve continuar exposto como token')
  assert.match(css, /@media\s*\(\s*min-width:\s*\$sidebar-breakpoint\s*\+\s*1\s*\)/, 'desktop deve usar o token + 1')
  assert.match(css, /@media\s*\(\s*max-width:\s*\$sidebar-breakpoint\s*\)/, 'mobile deve usar o mesmo token')
  assert.doesNotMatch(withoutToken, /\b1024px\b|\b1025px\b/, 'nenhum novo breakpoint numérico pode ser criado')
})
