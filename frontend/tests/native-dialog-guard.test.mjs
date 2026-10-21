import { test } from 'node:test'
import { strict as assert } from 'node:assert'
import { readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const sourceRoot = join(root, 'src')
const WINDOW_CALL = /\b(?:window|globalThis)\.(confirm|alert|prompt)\s*\(/g
const DIRECT_CALL = /(?<!\.)\b(confirm|alert|prompt)\s*\(/g

function walk(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) return walk(path)
    return /\.(vue|js|ts)$/.test(entry.name) ? [path] : []
  })
}

function lineAt(source, index) {
  return source.slice(0, index).split('\n').length
}

export function violationsIn(source, file = 'sample.js') {
  const found = []
  for (const pattern of [WINDOW_CALL, DIRECT_CALL]) {
    for (const match of source.matchAll(pattern)) {
      if (file.endsWith('src/store/dialog.js') && pattern === DIRECT_CALL) continue
      found.push(`${file}:${lineAt(source, match.index)}: ${match[0].trim()}`)
    }
  }
  return found
}

test('a guarda reprova window.confirm/alert/prompt e chamadas diretas', () => {
  const sample = "window.confirm('x')\nconfirm('y')\ndialog.alert({})\ndialog.confirm({})"
  assert.deepEqual(violationsIn(sample, 'sample.js'), [
    'sample.js:1: window.confirm(',
    'sample.js:2: confirm(',
  ])
})

test('dialog.alert e dialog.confirm continuam permitidos', () => {
  assert.deepEqual(violationsIn('dialog.alert({ title: "x" })\ndialog.confirm({})'), [])
})

test('nenhum arquivo de src usa diálogo nativo', () => {
  const violations = walk(sourceRoot).flatMap((file) =>
    violationsIn(readFileSync(file, 'utf8'), file.slice(root.length + 1)),
  )
  assert.deepEqual(violations, [], `diálogos nativos encontrados:\n${violations.join('\n')}`)
})
