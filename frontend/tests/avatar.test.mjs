import { test } from 'node:test'
import { strict as assert } from 'node:assert'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const read = (relative) => readFileSync(join(root, relative), 'utf8')

const block = (css, selector) => {
  const match = new RegExp(`${selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\{([^}]*)\\}`).exec(css)
  assert.ok(match, `bloco ${selector} existe`)
  return match[1]
}

test('avatar: borda circular e recorte central vêm do CSS', () => {
  const css = read('src/styles/main.scss')
  assert.match(block(css, '.avatar'), /border-radius:\s*50%/, '.avatar circular')
  const image = block(css, '.avatar img')
  assert.match(image, /object-fit:\s*cover/, 'object-fit cover')
  assert.match(image, /object-position:\s*center/, 'object-position center')
})

test('avatar: tamanhos 22/42/64', () => {
  const css = read('src/styles/main.scss')
  assert.match(block(css, '.avatar.sm'), /width:\s*22px/)
  assert.match(block(css, '.avatar.md'), /width:\s*42px/)
  assert.match(block(css, '.avatar.lg'), /width:\s*64px/)
})

test('avatar: seed traz o retrato de Vivy em PNG', () => {
  const seed = read('src/data/seed.js')
  const portrait = read('src/data/seed-portrait.js')
  assert.match(seed, /import \{ VIVY_PORTRAIT \} from '\.\/seed-portrait\.js'/)
  assert.match(seed, /portrait:\s*VIVY_PORTRAIT/)
  assert.match(portrait, /^export const VIVY_PORTRAIT = 'data:image\/png;base64,[A-Za-z0-9+/=]+'\s*$/m)
})

test('avatar: nenhuma dependência nova no package.json', () => {
  const packageJson = JSON.parse(read('package.json'))
  const deps = Object.keys(packageJson.dependencies || {})
  const devDeps = Object.keys(packageJson.devDependencies || {})
  
  // Verifica que não há novas dependências (deve ser um subconjunto do conjunto original)
  const expectedDeps = [
    '@vitejs/plugin-vue',
    'autoprefixer',
    'cypress',
    'postcss',
    'prettier',
    'tailwindcss',
    'vitest',
    'vue',
    'vue-router',
    'ws'
  ]
  
  const expectedDevDeps = [
    '@types/node',
    '@vitejs/plugin-vue',
    '@vue/test-utils',
    'cypress',
    'jsdom',
    'msw',
    'postcss',
    'tailwindcss',
    'typescript',
    'vitest'
  ]
  
  // Não devem existir dependências novas
  for (const dep of deps) {
    assert.ok(expectedDeps.includes(dep), `Dependência nova detectada: ${dep}`)
  }
  
  for (const dep of devDeps) {
    assert.ok(expectedDevDeps.includes(dep), `Dependência de desenvolvimento nova detectada: ${dep}`)
  }
})
