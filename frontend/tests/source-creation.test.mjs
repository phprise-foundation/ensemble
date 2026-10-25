import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const sidebarVue = readFileSync(join(here, '..', 'src', 'components', 'AppSidebar.vue'), 'utf8')

function sourceBlock() {
  const match = sidebarVue.match(/if \(current\.kind === 'source'[\s\S]*?\n    \}/)
  return match ? match[0] : ''
}

test('new source draft starts without id', () => {
  assert.match(
    sidebarVue,
    /function addSource\(\) \{[\s\S]*?id: '', name: '', provider: 'github', url: '', baseBranch: 'main', workspaceId: workspaceId\.value/,
    'a source nova deve ser aberta com id vazio'
  )
})

test('source creation decides the verb from the existing fact', () => {
  const block = sourceBlock()
  assert.ok(block, 'deve existir o bloco de tratamento de source')
  assert.match(
    block,
    /current\.draft\.__existing && !current\.draft\.id[\s\S]*?current\.draft\.id = slugify\(current\.draft\.name\)/,
    'o id só pode ser derivado quando a source já existe; source nova deve chegar com id vazio ao saveEntity'
  )
})

test('source save routes through the correct collection', () => {
  assert.match(sidebarVue, /source: 'sources'/, 'deve mapear kind source para collection sources')
  assert.match(
    sidebarVue,
    /await boardStore\.saveEntity\(collectionOf\(current\.kind\), current\.draft\)/,
    'o salvamento deve usar a collection resolvida pelo kind'
  )
})
