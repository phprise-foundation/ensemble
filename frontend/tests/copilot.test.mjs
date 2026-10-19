import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const copilotVue = readFileSync(join(here, '..', 'src', 'components', 'AppCopilot.vue'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const iconsVue = readFileSync(join(here, '..', 'src', 'components', 'AppIcon.vue'), 'utf8')

const shortcutHandler = () => {
  const match = appVue.match(/function\s+handleKeyboardShortcut\s*\(event\)\s*\{([\s\S]*?)\n\}/)
  return match ? match[1] : ''
}

test('AppCopilot.vue nao acopla rede, armazenamento nem icone', () => {
  assert.doesNotMatch(copilotVue, /fetch|axios|XMLHttpRequest|\/api\/|CHAT_URL/)
  assert.doesNotMatch(copilotVue, /localStorage|sessionStorage|store/)
  assert.doesNotMatch(copilotVue, /v-html/)
  assert.doesNotMatch(copilotVue, /AppIcon/)
})

test('App.vue nasce expandido e sem persistencia', () => {
  assert.match(appVue, /const copilotOpen = ref\(true\)/)
  assert.doesNotMatch(appVue, /localStorage/)
  assert.match(appVue, /<AppCopilot v-if="!isMobile && copilotOpen" \/>/)
})

test('App.vue monta o painel como irmao de .content', () => {
  assert.match(appVue, /<\/div>\s*<AppCopilot v-if="!isMobile && copilotOpen" \/>/)
  const main = appVue.indexOf('<main class="main">')
  const mainEnd = appVue.indexOf('</main>')
  assert.ok(main !== -1 && mainEnd !== -1)
  assert.ok(appVue.indexOf('<AppCopilot', main) > mainEnd)
})

test('botao .copilot-toggle tem ARIA e e desktop-only', () => {
  assert.match(appVue, /class="icon-btn copilot-toggle"/)
  assert.match(appVue, /aria-label="Toggle copilot"/)
  assert.match(appVue, /:aria-expanded="copilotOpen"/)
  assert.match(appVue, /aria-controls="app-copilot"/)
  assert.match(appVue, /v-if="!isMobile"\s+data-topbar-slot="grid-right"\s+class="icon-btn copilot-toggle"/)
})

test('atalho Ctrl+Alt+B consulta altKey antes do ramo do Ctrl+B', () => {
  const handler = shortcutHandler()
  assert.ok(handler, 'handleKeyboardShortcut deve existir em App.vue')
  const copilotBranch = handler.indexOf('altKey')
  const sidebarBranch = handler.indexOf('sidebarCollapsed')
  assert.ok(copilotBranch !== -1)
  assert.ok(sidebarBranch !== -1)
  assert.ok(copilotBranch < sidebarBranch)
  assert.match(handler, /isMobile\.value/)
})

test('largura do painel usa token proprio min(25vw, 400px)', () => {
  assert.match(css, /--copilot-width:\s*min\(25vw,\s*400px\)/)
  const panelBlock = css.match(/(^|\n)\.copilot-panel\s*\{[^}]*\}/)
  assert.ok(panelBlock)
  assert.match(panelBlock[0], /width:\s*var\(--copilot-width\)/)
  assert.doesNotMatch(panelBlock[0], /--panel-width/)
})

test('AppIcon.vue mapeia copilot para PanelRight (Lucide)', () => {
  assert.match(iconsVue, /copilot:\s*PanelRight/)
})
