import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const aqui = dirname(fileURLToPath(import.meta.url))
const config = readFileSync(join(aqui, '..', 'cypress.config.js'), 'utf8')
const spec = readFileSync(
  join(aqui, '..', 'cypress', 'e2e', 'icon-standard.cy.js'),
  'utf8',
)

test('o portão visualAudit é publicado em expose com E2E_VISUAL_AUDIT', () => {
  assert.match(config, /expose:\s*\{\s*visualAudit:\s*process\.env\.E2E_VISUAL_AUDIT \|\| '0'/)
  assert.doesNotMatch(config, /\benv:\s*\{/)
  assert.doesNotMatch(config, /Cypress\.env\(/)
})

test('o spec lê o portão por chave via Cypress.expose', () => {
  assert.match(spec, /Cypress\.expose\('visualAudit'\) !== '1'/)
})

test('a captura tem teto explícito e não há skip silencioso', () => {
  assert.match(spec, /cy\.screenshot\(name,\s*\{\s*timeout:\s*TETO_CAPTURA_MS\s*\}\)/)
  assert.match(spec, /const TETO_CAPTURA_MS = 20000/)
  assert.doesNotMatch(spec, /\b(?:this\.)?skip\(/)
  assert.doesNotMatch(spec, /\bcy\.skip\b/)
})

test('existe a guarda de não-repetição da etapa C', () => {
  assert.match(spec, /let capturasQuebradas = false/)
  assert.match(spec, /capturasQuebradas\)\s*\{\s*\n\s*throw new Error\(/)
  assert.match(spec, /Cypress\.on\('fail', \(erro\) => \{/)
  assert.match(spec, /estourou o teto de \$\{TETO_CAPTURA_MS\}ms/)
})
