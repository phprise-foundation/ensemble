import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const feature = readFileSync(join(here, 'topbar-order.feature'), 'utf8')
const spec = readFileSync(join(here, 'topbar-order.spec.js'), 'utf8')
const e2e = readFileSync(join(here, '..', 'cypress', 'e2e', 'topbar-order.cy.js'), 'utf8')

const actionsBlock = () => {
  const start = appVue.indexOf('<div class="topbar-actions">')
  const end = appVue.indexOf('</header>')
  assert.ok(start !== -1 && end > start, 'o grupo .topbar-actions deve existir no topbar')
  return appVue.slice(start, end)
}

const slots = () => [...actionsBlock().matchAll(/data-topbar-slot="([^"]+)"/g)].map((match) => match[1])

const consoleInTree = () => /AppConsole|class="icon-btn console-toggle"|app-console/.test(appVue)

const expectedSequence = () =>
  consoleInTree()
    ? ['sim', 'grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile']
    : ['sim', 'grid-left', 'grid-right', 'theme', 'profile']

test('o grupo .topbar-actions fica depois do titulo e do spacer', () => {
  const actions = appVue.indexOf('<div class="topbar-actions">')
  assert.ok(appVue.indexOf('<h1>{{ route.meta.title') < actions, 'o titulo precede o grupo')
  assert.ok(appVue.indexOf('<div class="spacer"></div>') < actions, 'o spacer precede o grupo')
})

test('os slots seguem a sequencia canonica derivada da arvore', () => {
  assert.deepEqual(slots(), expectedSequence())
})

test('nenhum controle de grade fica fora do grupo .topbar-actions', () => {
  const block = actionsBlock()
  const outside = appVue.slice(0, appVue.indexOf(block)) + appVue.slice(appVue.indexOf(block) + block.length)
  for (const control of ['sidebar-toggle', 'console-toggle', 'copilot-toggle', 'sim-toggle', 'theme-toggle']) {
    assert.doesNotMatch(outside, new RegExp(`class="[^"]*${control}`), `${control} so pode existir dentro do grupo`)
  }
})

test('todas as grades sao desktop-only e o grupo e o unico container', () => {
  const block = actionsBlock()
  for (const slot of ['grid-left', 'grid-bottom', 'grid-right']) {
    assert.match(block, new RegExp(`v-if="!isMobile"\\s+data-topbar-slot="${slot}"`), `${slot} deve ser desktop-only`)
  }
})

test('Sim e botao de alternancia com aria-pressed e dot visivel', () => {
  const block = actionsBlock()
  assert.match(block, /data-topbar-slot="sim"\s+class="button ghost sim-toggle"/)
  assert.match(block, /:aria-pressed="hasSimulation\.value \? 'true' : 'false'"/)
  assert.match(block, /<span class="sim-dot sm"><\/span>/)
})

test('grades declaram aria-expanded e aria-controls coerentes', () => {
  const block = actionsBlock()
  assert.match(block, /data-topbar-slot="grid-left"[\s\S]*?aria-controls="app-sidebar"[\s\S]*?:aria-expanded="!sidebarCollapsed"/)
  assert.match(block, /data-topbar-slot="grid-bottom"[\s\S]*?:aria-expanded="consoleOpen"[\s\S]*?aria-controls="app-console"/)
  assert.match(block, /data-topbar-slot="grid-right"[\s\S]*?:aria-expanded="copilotOpen"[\s\S]*?aria-controls="app-copilot"/)
})

test('tema tem aria-label, titulo e classe propria e o perfil fecha o grupo', () => {
  const block = actionsBlock()
  assert.match(block, /data-topbar-slot="theme"\s+class="icon-btn theme-toggle"/)
  assert.match(block, /:aria-label="theme\.mode === 'dark' \? 'Light theme' : 'Dark theme'"/)
  assert.match(block, /<ProfileMenu data-topbar-slot="profile" \/>\s*<\/div>/)
})

test('CSS fixa o grupo, deixa o titulo truncar e esconde as grades no mobile', () => {
  const actions = css.match(/(^|\n)\.topbar-actions\s*\{[^}]*\}/)
  assert.ok(actions, '.topbar-actions deve ter regra propria')
  assert.match(actions[0], /flex:\s*0 0 auto/)
  assert.match(css, /\.topbar h1\s*\{[\s\S]*?min-width:\s*0[\s\S]*?text-overflow:\s*ellipsis/)
  assert.match(css, /\n\.sidebar-toggle\s*\{[^}]*display:\s*none/)
  assert.match(css, /@media\s*\(\s*min-width\s*:\s*\$sidebar-breakpoint\s*\+\s*1\s*\)\s*\{[\s\S]*?\.sidebar-toggle\s*\{[\s\S]*?display:\s*inline-flex/)
})

test('feature, spec e E2E cobrem ordem, breakpoint e acoes', () => {
  assert.match(feature, /Cenário: ordem completa em desktop/)
  assert.match(feature, /Cenário: tela mobile não exibe grades/)
  assert.match(spec, /topbar-actions/)
  assert.match(e2e, /data-topbar-slot/)
  assert.match(e2e, /--breakpoint-sidebar/)
})
