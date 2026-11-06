import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const frontend = join(here, '..')
const root = join(here, '..', '..')

test('o index usa o base URL do Vite para o favicon', () => {
  const html = readFileSync(join(frontend, 'index.html'), 'utf8')

  assert.match(html, /href="%BASE_URL%favicon\.svg"/)
  assert.doesNotMatch(html, /href="\.\/favicon\.svg"/)
})

test('o favicon bluesmoke existe na raiz pública e no diretório do frontend', () => {
  const rootFavicon = readFileSync(join(root, 'public', 'favicon.svg'), 'utf8')
  const frontendFavicon = readFileSync(join(frontend, 'public', 'favicon.svg'), 'utf8')

  assert.match(rootFavicon, /#7faacc/)
  assert.equal(rootFavicon, frontendFavicon)
})
