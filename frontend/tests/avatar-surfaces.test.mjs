import { test } from 'node:test'
import { strict as assert } from 'node:assert'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const read = (relative) => readFileSync(join(root, relative), 'utf8')

test('AgentsPage e IssuePanel consomem o Avatar compartilhado', () => {
  const agents = read('src/views/AgentsPage.vue')
  assert.match(agents, /import Avatar from '\.\.\/components\/Avatar\.vue'/, 'AgentsPage importa Avatar')
  assert.match(agents, /<Avatar/, 'AgentsPage renderiza Avatar')

  const panel = read('src/components/IssuePanel.vue')
  assert.match(panel, /import Avatar from '\.\/Avatar\.vue'/, 'IssuePanel importa Avatar')
  assert.match(panel, /<Avatar/, 'IssuePanel renderiza Avatar')
})

test('o código não voltou a usar .portrait, backgroundImage de agente ou (3x4)', () => {
  for (const file of ['src/views/AgentsPage.vue', 'src/components/IssuePanel.vue']) {
    const source = read(file)
    assert.doesNotMatch(source, /class="[^"]*portrait/, `${file} sem classe .portrait`)
    assert.doesNotMatch(source, /backgroundImage:\s*`url\(\$\{[^}]*portrait/, `${file} sem backgroundImage de agente`)
    assert.doesNotMatch(source, /\(3x4\)/, `${file} sem (3x4)`)
  }
  const styles = read('src/styles/main.scss')
  assert.doesNotMatch(styles, /^\.portrait(\.lg)?\s*\{/m, 'SCSS sem seletor .portrait')
})

test('a fixture do retrato existe e tem proporção 3x4', () => {
  const png = readFileSync(join(root, 'cypress/fixtures/agent-portrait-3x4.png'))
  assert.equal(png.readUInt32BE(16), 120, 'largura da fixture')
  assert.equal(png.readUInt32BE(20), 160, 'altura da fixture')
  assert.equal(png.readUInt32BE(20) / png.readUInt32BE(16), 4 / 3, 'proporção 3x4')
})
