import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import assert from 'node:assert/strict'

const styles = readFileSync(new URL('../src/styles/main.scss', import.meta.url), 'utf8')

test('declara um único token de 768px para o corte mobile', () => {
  const declarations = styles.match(/\$mobile-breakpoint:\s*\d+px/g) ?? []
  assert.equal(declarations.length, 1)
  assert.equal(declarations[0], '$mobile-breakpoint: 768px')
  assert.match(styles, /--breakpoint-mobile:\s*#\{\$mobile-breakpoint\}/)
})

test('o corte da tela cheia é estrito e fica abaixo do bloco de 1024px', () => {
  assert.match(styles, /@media \(max-width: \$mobile-breakpoint - 1\)/)
  assert.doesNotMatch(styles, /@media \(max-width: \$mobile-breakpoint\)/)
})

test('tela cheia usa inset zero e largura 100%, sem 100vw', () => {
  assert.match(styles, /\.sidebar\s*\{\s*inset: 0;\s*width: 100%;\s*flex: none;\s*\}/)
  assert.doesNotMatch(styles, /100vw/)
})

test('trava o scroll do body pela classe drawer-locked', () => {
  assert.match(styles, /body\.drawer-locked\s*\{\s*overflow: hidden;\s*\}/)
})
