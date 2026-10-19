import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const copilotVue = readFileSync(join(here, '..', 'src', 'components', 'AppCopilot.vue'), 'utf8')
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const iconsVue = readFileSync(join(here, '..', 'src', 'components', 'AppIcon.vue'), 'utf8')

test('AppCopilot.vue não acopla rede nem store', () => {
  assert.doesNotMatch(copilotVue, /fetch|axios|\/api\/|CHAT_URL/, 'o painel do copilot não pode chamar rede nesta rodada')
  assert.doesNotMatch(copilotVue, /store|localStorage/, 'o painel do copilot não pode depender de store ou persistência')
  assert.doesNotMatch(copilotVue, /v-html/, 'o painel do copilot não pode usar v-html')
})

test('contrato do esqueleto do painel', () => {
  assert.match(copilotVue, /<section id="app-copilot" class="copilot-panel">/, 'a raiz do painel é a section app-copilot')
  assert.match(copilotVue, /class="copilot-header"/, 'o header do copilot deve existir')
  assert.match(copilotVue, /class="copilot-body"/, 'o corpo placeholder do copilot deve existir')
})

test('App.vue nasce expandido e sem persistência', () => {
  assert.match(appVue, /const\s+copilotOpen\s*=\s*ref\s*\(\s*true\s*\)/, 'o painel do copilot nasce expandido')
  assert.doesNotMatch(appVue, /localStorage/, 'não há persistência da escolha nesta rodada')
  assert.match(appVue, /copilotOpen\.value\s*=\s*!copilotOpen\.value/, 'o atalho alterna o mesmo estado do clique')
  assert.match(appVue, /<AppCopilot v-if="!isMobile && copilotOpen" \/>/, 'o painel só é montado no desktop com estado expandido')
})

test('botão de toggle com ARIA completo e desktop-only', () => {
  assert.match(appVue, /class="icon-btn copilot-toggle"/, 'o botão copilot-toggle deve existir em App.vue')
  assert.match(appVue, /aria-label="Toggle copilot"/, 'o botão deve ter aria-label="Toggle copilot"')
  assert.match(appVue, /:aria-expanded="copilotOpen"/, 'o botão deve expor aria-expanded ligado ao estado')
  assert.match(appVue, /aria-controls="app-copilot"/, 'o botão deve apontar o painel em aria-controls')
  assert.match(appVue, /v-if="!isMobile"[\s\S]{0,240}class="icon-btn copilot-toggle"/, 'o botão do copilot é desktop-only')
})

test('atalho Ctrl+Alt+B consulta altKey antes do ramo do Ctrl+B', () => {
  const handler = appVue.match(/function\s+handleKeyboardShortcut\s*\(event\)\s*\{([\s\S]*?)\n\}/)
  assert.ok(handler, 'o atalho de teclado deve existir em App.vue')
  const copilotBranch = handler[1].indexOf('altKey')
  const sidebarBranch = handler[1].indexOf('sidebarCollapsed.value')
  assert.ok(copilotBranch !== -1, 'o ramo do copilot deve checar event.altKey')
  assert.ok(sidebarBranch !== -1, 'o ramo do sidebar deve existir no mesmo handler')
  assert.ok(copilotBranch < sidebarBranch, 'o ramo do copilot deve vir antes do ramo do Ctrl+B, senão um atalho dispara os dois toggles')
  assert.match(handler[1], /isMobile\.value/, 'o atalho deve consultar o viewport antes de alternar o estado')
})

test('largura do painel usa token próprio min(25vw, 400px)', () => {
  assert.match(css, /--copilot-width:\s*min\(25vw,\s*400px\)/, 'o token --copilot-width deve ser min(25vw, 400px) no :root')
  const panelBlock = css.match(/\.copilot-panel\s*\{[^}]*\}/)
  assert.ok(panelBlock, 'o bloco .copilot-panel deve existir')
  assert.match(panelBlock[0], /width:\s*var\(--copilot-width\)/, '.copilot-panel deve consumir o token próprio')
  assert.match(css, /--copilot-width:\s*min\(25vw,\s*400px\)/, 'o token --copilot-width deve ser min(25vw, 400px) no :root')
  assert.doesNotMatch(panelBlock[0], /--panel-width/, 'não é permitido reutilizar --panel-width')
})

test('dicionário de ícones expõe a chave copilot (onda 1)', () => {
  assert.match(iconsVue, /copilot:\s*\[\s*'M17 3h4v18h-4z',\s*'M3 3h11v18H3z'\s*\]/, 'a chave copilot deve existir com os paths do contrato da KAN-62')
})
