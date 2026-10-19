import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')

function ruleBody(selector) {
  const start = css.indexOf(selector)
  if (start === -1) {
    return ''
  }
  const open = css.indexOf('{', start)
  const close = css.indexOf('}', open)
  return open === -1 || close === -1 ? '' : css.slice(open + 1, close)
}

function tokens(blockBody) {
  const found = new Map()
  for (const match of blockBody.matchAll(/(--[\w-]+)\s*:\s*([^;}\n]+)/g)) {
    found.set(match[1], match[2].trim().toLowerCase())
  }
  return found
}

function channelValue(hex, offset) {
  const value = Number.parseInt(hex.replace('#', '').slice(offset, offset + 2), 16) / 255
  return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
}

function luminance(hex) {
  return 0.2126 * channelValue(hex, 0) + 0.7152 * channelValue(hex, 2) + 0.0722 * channelValue(hex, 4)
}

function contrastRatio(foreground, background) {
  const a = luminance(foreground)
  const b = luminance(background)
  const [lighter, darker] = a >= b ? [a, b] : [b, a]
  return (lighter + 0.05) / (darker + 0.05)
}

function tokenMismatches(expected, actual) {
  const mismatches = []
  for (const [name, value] of Object.entries(expected)) {
    const found = actual.get(name)
    if (found !== value) {
      mismatches.push(`${name}: esperado ${value}, encontrado ${found ?? 'ausente'}`)
    }
  }
  return mismatches
}

const dark = tokens(ruleBody(":root[data-theme='dark']"))
const light = tokens(ruleBody(":root[data-theme='light']"))

const palette = {
  '--bg': '#1e1e1e',
  '--surface': '#252526',
  '--surface-2': '#2d2d2d',
  '--surface-3': '#333333',
  '--border': '#454545',
  '--text': '#d4d4d4',
  '--muted': '#858585',
  '--accent': '#3794ff',
  '--accent-contrast': '#ffffff',
  '--selection': '#264f78',
}

const syntax = {
  '--syntax-string': '#ce9178',
  '--syntax-keyword': '#c586c0',
  '--syntax-function': '#dcdcaa',
  '--syntax-type': '#4ec9b0',
  '--syntax-comment': '#6a9955',
  '--syntax-number': '#b5cea8',
}

const frozenLight = {
  '--bg': '#f4f5f7',
  '--surface': '#ffffff',
  '--surface-2': '#f0f2f5',
  '--surface-3': '#e9ecf1',
  '--border': '#dfe1e6',
  '--text': '#172b4d',
  '--muted': '#6b778c',
  '--accent': '#0052cc',
  '--accent-contrast': '#ffffff',
}

const minAa = 4.5

test('tokens base fiéis ao Dark+', () => {
  const mismatches = tokenMismatches(palette, dark)
  assert.equal(mismatches.length, 0, `tokens divergentes do Dark+: ${mismatches.join('; ')}`)
})

test('tokens de syntax prontos para blocos de código', () => {
  const mismatches = tokenMismatches(syntax, dark)
  assert.equal(mismatches.length, 0, `tokens de syntax divergentes: ${mismatches.join('; ')}`)
})

test('seleção de texto usa o destaque do Dark+', () => {
  assert.ok(dark.has('--selection'), 'o token --selection deve estar declarado no tema escuro')
  const selectionBody = ruleBody('::selection')
  assert.match(selectionBody, /background\s*:\s*var\(--selection\)/, 'o fundo do ::selection deve usar var(--selection)')
  assert.match(selectionBody, /color\s*:\s*var\(--text\)/, 'o texto do ::selection deve usar var(--text)')
  const ratio = contrastRatio(dark.get('--text'), dark.get('--selection'))
  assert.ok(ratio >= minAa, `--text sobre --selection = ${ratio.toFixed(2)}:1 (mínimo ${minAa}:1)`)
})

test('destaque de item do quadro usa a cor de seleção', () => {
  const uses = css.match(/var\(--selection\)/g) ?? []
  assert.ok(
    uses.length >= 2,
    `var(--selection) aparece ${uses.length}x; o ::selection e pelo menos um estado de destaque de item do quadro devem usá-lo`
  )
})

test('contraste do texto atende WCAG AA no tema escuro', () => {
  const pairs = [
    ['--text', '--bg'],
    ['--text', '--surface'],
    ['--text', '--surface-2'],
    ['--text', '--surface-3'],
    ['--accent', '--bg'],
    ['--accent', '--surface'],
    ['--muted', '--bg'],
  ]
  const failures = []
  for (const [foregroundName, backgroundName] of pairs) {
    const foreground = dark.get(foregroundName)
    const background = dark.get(backgroundName)
    if (foreground === undefined || background === undefined) {
      failures.push(`${foregroundName}/${backgroundName}: token ausente`)
      continue
    }
    const ratio = contrastRatio(foreground, background)
    if (ratio < minAa) {
      failures.push(`${foregroundName} sobre ${backgroundName} = ${ratio.toFixed(2)}:1 (mínimo ${minAa}:1)`)
    }
  }
  assert.equal(failures.length, 0, `pares abaixo de AA: ${failures.join('; ')}`)
})

test('seleção de texto continua com cor definida no tema claro', () => {
  const scopedToDark = /:root\[data-theme='dark'\][^{]*::selection\s*\{/.test(css)
  const selectedInLight = light.has('--selection')
  assert.ok(
    scopedToDark || selectedInLight,
    'a ::selection não pode ficar órfã no tema claro: escope a regra em :root[data-theme=dark] ou declare --selection no bloco do tema claro'
  )
})

test('tema claro congelado (sem regressão de paleta)', () => {
  const mismatches = tokenMismatches(frozenLight, light)
  assert.equal(mismatches.length, 0, `tema claro mudou sem deliberação: ${mismatches.join('; ')}`)
})

test('tokens de estado permanecem no tema escuro', () => {
  for (const name of ['--danger', '--warn', '--ok']) {
    assert.ok(dark.has(name), `${name} deve continuar declarado no bloco do tema escuro`)
  }
})
