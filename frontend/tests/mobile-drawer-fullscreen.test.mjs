import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
const appVue = readFileSync(join(here, '..', 'src', 'App.vue'), 'utf8')
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

function fullscreenBlock(target) {
  const start = target.indexOf('max-width: $mobile-breakpoint - 1')
  assert.notEqual(start, -1, 'o bloco de tela cheia deve existir no SCSS')
  return target.slice(start, target.indexOf('/* Board */', start))
}

test('uma única fonte para o corte de 768px', () => {
  assert.match(css, /\$mobile-breakpoint:\s*768px\s*;/, 'o SCSS deve declarar o token $mobile-breakpoint')
  assert.match(css, /--breakpoint-mobile:\s*#\{\$mobile-breakpoint\}\s*;/, 'o :root deve publicar o token CSS')
  assert.doesNotMatch(css, /@media[^\n]*768px/, 'a media query deve consumir o token, não o literal')
  assert.doesNotMatch(css, /max-width:\s*767px/, 'o corte deve sair do token com offset, não de um literal')
  assert.doesNotMatch(
    css,
    /max-width:\s*\$mobile-breakpoint\s*\)\s*\{[^]*\.sidebar\s*\{[^]*inset:\s*0\s*;/,
    'a media query de tela cheia não pode ser max-width direto em 768px'
  )
})

test('media query estrita em tela cheia', () => {
  assert.match(
    css,
    /@media\s*\(\s*max-width\s*:\s*\$mobile-breakpoint\s*-\s*1\s*\)/,
    'o corte em tela cheia deve ser max-width: $mobile-breakpoint - 1'
  )
})

test('geometria de tela cheia: inset 0, width 100%, sem 100vw', () => {
  const block = fullscreenBlock(css)
  assert.match(
    block,
    /\.sidebar\s*\{\s*inset:\s*0\s*;\s*width:\s*100%\s*;[^}]*flex:\s*none\s*;/,
    'o painel em tela cheia deve usar inset: 0 e width: 100%'
  )
  assert.doesNotMatch(block, /100vw|100vh/, 'tela cheia não deve usar 100vw nem 100vh')
})

test('alvos de toque de 44px e botão de fechar na faixa estreita', () => {
  const block = fullscreenBlock(css)
  assert.match(
    block,
    /\.sidebar\s+\.nav-entry,\s*\n\s*\.sidebar\s+\.leaf\s*\{[^}]*min-height:\s*44px\s*;/,
    'nav-entry e leaf devem ter alvo de toque de 44px'
  )
  assert.match(block, /\.sidebar\s+\.icon-btn\s*\{[^}]*width:\s*44px\s*;\s*height:\s*44px\s*;/, 'icon-btn deve ter 44px')
  assert.match(block, /\.sidebar-close\s*\{[^}]*margin-left:\s*auto\s*;/, 'o botão de fechar deve ir para a direita')
})

test('scroll lock por classe no body', () => {
  assert.match(css, /body\.drawer-locked\s*\{[^}]*overflow:\s*hidden\s*;/, 'o lock deve ser a classe drawer-locked')
  assert.match(appVue, /\.drawer-locked'|classList\.toggle\([^)]*drawer-locked/, 'App.vue deve controlar a classe drawer-locked')
  assert.match(
    appVue,
    /document\.body\.classList\.remove\(\s*'drawer-locked'\s*\)/,
    'o unmount deve remover a classe drawer-locked'
  )
  assert.doesNotMatch(appVue, /body\.style\.overflow/, 'o lock não deve ser estilo inline')
})

test('estado narrow lê o token computado com offset -1', () => {
  assert.match(appVue, /const isNarrow = ref\(false\)/, 'App.vue deve manter o estado isNarrow')
  assert.match(appVue, /mediaQueryFor\('--breakpoint-sidebar'\)/, 'a faixa de 1024px deve sair do token CSS')
  assert.match(
    appVue,
    /mediaQueryFor\('--breakpoint-mobile',\s*-1\)/,
    'a faixa de tela cheia deve sair do token com offset -1'
  )
  assert.doesNotMatch(appVue, /max-width:\s*\d+px/, 'App.vue não deve repetir larguras literais em matchMedia')
  assert.match(appVue, /:narrow="isNarrow"/, 'App.vue deve passar isNarrow ao sidebar')
})

test('botão dedicado de fechar visível só na faixa estreita', () => {
  assert.match(
    sidebarVue,
    /narrow:\s*\{\s*type:\s*Boolean,\s*default:\s*false\s*\}/,
    'AppSidebar deve declarar a prop narrow'
  )
  assert.match(sidebarVue, /v-if="narrow && isMobile"/, 'o botão de fechar só aparece na tela cheia')
  assert.match(sidebarVue, /class="icon-btn sidebar-close"/, 'o botão de fechar deve ter a classe sidebar-close')
  assert.match(sidebarVue, /type="button"/, 'o botão de fechar deve ser type="button"')
  assert.match(sidebarVue, /aria-label="Fechar menu"/, 'o botão de fechar deve ter rótulo acessível')
  assert.match(sidebarVue, /@click="emit\('close'\)"/, 'o botão de fechar deve emitir close')
})
