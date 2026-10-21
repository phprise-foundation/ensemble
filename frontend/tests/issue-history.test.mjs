import { mkdtempSync, rmSync, writeFileSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { after, test } from 'node:test'

import { load } from '../plugin/fake-backend.js'
import { createSeed } from '../src/data/seed.js'

const here = dirname(fileURLToPath(import.meta.url))
const readSource = (relativePath) => readFileSync(join(here, '..', relativePath), 'utf-8')

const root = mkdtempSync(join(tmpdir(), 'kan-115-'))
after(() => {
  rmSync(root, { recursive: true, force: true })
})

const EVENT_TYPES = ['created', 'status', 'assignee', 'summary', 'description']

test('guarda: FEATURES mantém a feature history com rótulo History', () => {
  const source = readSource('src/api/simulation.js')
  assert.match(source, /\{\s*key:\s*'history',\s*label:\s*'History',?\s*\}/)
})

test('guarda: seed cobre eventos de history nas issues', () => {
  const seed = createSeed()
  const issues = seed.issues ?? []
  const withEvents = issues.filter((issue) => (issue.history ?? []).length > 0)
  const allEvents = withEvents.flatMap((issue) => issue.history)

  assert.ok(withEvents.length >= 4, `esperava ao menos 4 issues com eventos, achei ${withEvents.length}`)
  for (const type of EVENT_TYPES) {
    assert.ok(
      allEvents.some((event) => event.type === type),
      `o seed deixou de cobrir o tipo de evento ${type}`,
    )
  }
  assert.ok(
    issues.some((issue) => Array.isArray(issue.history) && issue.history.length === 0),
    'o seed precisa de pelo menos uma issue com history vazia (empty state)',
  )
  for (const event of allEvents) {
    assert.ok(event.actor, `evento sem actor: ${JSON.stringify(event)}`)
    assert.ok(!Number.isNaN(Date.parse(event.at)), `evento com at fora de ISO: ${JSON.stringify(event)}`)
  }
})

test('guarda: fake backend mantém o backfill por issue e o history vazio no POST', () => {
  const source = readSource('plugin/fake-backend.js')
  assert.match(source, /for \(const issue of database\.issues \?\? \[\]\)\s*\{\s*issue\.history \?\?= \[\]\s*\}/)
  assert.match(source, /history:\s*\[\]/)
})

test('guarda: move, PATCH e comentários não geram eventos de history', () => {
  const source = readSource('plugin/fake-backend.js')
  assert.doesNotMatch(source, /history\.push/)
})

test('backfill: db.json legado sem history ganha a chave vazia e preserva o resto', () => {
  const file = join(root, 'db-legado.json')
  const seed = createSeed()
  const firstIssue = seed.issues[0]
  delete firstIssue.history
  const keptComment = { id: 'c-antigo', author: 'user', body: 'antes da history', at: '2024-01-01T00:00:00.000Z' }
  firstIssue.comments = [keptComment]
  writeFileSync(file, JSON.stringify(seed, null, 2))

  const database = load(file)
  const issue = database.issues[0]
  assert.deepEqual(issue.history, [], 'a issue legada abre com history vazia em vez de quebrar a aba')
  assert.deepEqual(issue.comments, [keptComment], 'o backfill é aditivo e não toca nos comentários')
  assert.equal(Object.isFrozen(issue.history), false)

  const loadedAgain = load(file)
  assert.equal(loadedAgain.issues[0].history.length, 0, 'a normalização é idempotente em releitura')
})

test('backfill: chave presente nunca é sobrescrita', () => {
  const file = join(root, 'db-com-history.json')
  const seed = createSeed()
  const event = { type: 'created', actor: 'user', at: '2024-02-01T00:00:00.000Z', summary: 'Issue created' }
  seed.issues[0].history = [event]
  writeFileSync(file, JSON.stringify(seed, null, 2))

  const database = load(file)
  assert.equal(database.issues[0].history.length, 1)
  assert.deepEqual(database.issues[0].history[0], event)
})

test('guarda: IssuePanel mantém ARIA de abas, watch de reset e selo de simulação', () => {
  const source = readSource('src/components/IssuePanel.vue')
  for (const mark of [
    'role="tablist"',
    'role="tab"',
    'role="tabpanel"',
    ':aria-selected',
    'aria-controls',
    'aria-labelledby',
    ':tabindex',
    "watch(() => issue.value?.id",
    "simulated('history')",
  ]) {
    assert.ok(source.includes(mark), `IssuePanel.vue perdeu ${mark}`)
  }
})

test('guarda: IssueHistory mantém ordenação, empty state e fallback sem type', () => {
  const source = readSource('src/components/IssueHistory.vue')
  assert.match(source, /sort\(\(a,\s*b\) => Date\.parse\(b\.at\) - Date\.parse\(a\.at\)\)/)
  assert.ok(source.includes('No history yet'), 'IssueHistory.vue perdeu o empty state')
  assert.match(source, /data-history-type="event\.type \|\| 'status'"/)
  assert.ok(source.includes("event.from !== undefined"), 'IssueHistory.vue perdeu o fallback para evento sem type')
})

test('guarda: main.scss mantém foco visível nas abas e estilos da timeline', () => {
  const source = readSource('src/styles/main.scss')
  assert.ok(source.includes('.tab:focus-visible'), 'main.scss perdeu o foco visível das abas')
  for (const selector of ['.history-list', '.history-entry', '.history-icon', '.history-text', '.history-meta']) {
    assert.ok(source.includes(selector), `main.scss perdeu ${selector}`)
  }
})

test('guarda: nenhum v-html no painel nem na timeline', () => {
  for (const file of ['src/components/IssuePanel.vue', 'src/components/IssueHistory.vue']) {
    const source = readSource(file)
    assert.doesNotMatch(source, /v-html/)
  }
})
