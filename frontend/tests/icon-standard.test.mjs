import { readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const here = dirname(fileURLToPath(import.meta.url))
const srcRoot = join(here, '..', 'src')

const vueFiles = (base = srcRoot) =>
  readdirSync(base, { withFileTypes: true }).flatMap((entry) =>
    entry.isDirectory()
      ? vueFiles(join(base, entry.name))
      : entry.name.endsWith('.vue') ? [
          join(base, entry.name).slice(srcRoot.length + 1),
        ] : [],
  )

const sourceOf = (file) => readFileSync(join(srcRoot, file), 'utf8')
const appIcon = sourceOf('components/AppIcon.vue')

const MAPPING = {
  board: 'Kanban', workspace: 'AppWindow', folder: 'Folder',
  layers: 'Layers', agents: 'Users', roles: 'Shield', profiles: 'Star',
  statuses: 'Clock', transitions: 'ArrowRight', issues: 'FileText',
  experiences: 'Bookmark', plus: 'Plus', chevron: 'ChevronRight',
  sun: 'Sun', moon: 'Moon', close: 'X', delete: 'Trash2',
  edit: 'Pencil', check: 'Check', menu: 'Menu', comment: 'MessageSquare',
  warning: 'TriangleAlert', sidebar: 'PanelLeft', copilot: 'PanelRight',
  console: 'SquareTerminal',
}

const BUTTON = /<button\b([\s\S]*?)<\/button>/g

const buttonBodies = (source) =>
  [...source.matchAll(BUTTON)].map(([, body]) => body)

const iconOnlyButtons = (source) =>
  buttonBodies(source).filter((body) =>
    /^(?:\s*<AppIcon[^>]*\/>\s*)+$/.test(body.slice(body.indexOf('>') + 1)))

test('só AppIcon.vue contém svg de UI em frontend/src', () => {
  for (const file of vueFiles()) {
    if (file === 'components/AppIcon.vue') {
      assert.match(appIcon, /<svg/)
      continue
    }
    assert.doesNotMatch(sourceOf(file), /<svg/, `${file} desenha svg manual`)
  }
})

test('todo name estático existe no mapa', () => {
  for (const file of vueFiles()) {
    for (const match of sourceOf(file).matchAll(/name="\w+"/g)) {
      const name = match[0].slice(6, -1)
      if (name === 'smartboard') continue
      assert.ok(name in MAPPING, `nome sem mapa: ${match[0]} (${file})`)
    }
  }
})

test('ternário sun/moon do tema está afirmado explicitamente', () => {
  assert.match(appIcon, /\bsun:\s*Sun\b/)
  assert.match(appIcon, /\bmoon:\s*Moon\b/)
  assert.match(sourceOf('App.vue'), /'dark' \? 'sun' : 'moon'/)
})

test('todo size estático pertence à escala 14/16/18/20 e a ausência só existe no topbar', () => {
  const callsOf = (source) => [...source.matchAll(/<AppIcon\b[^>]*>/g)]
  for (const file of vueFiles()) {
    for (const call of callsOf(sourceOf(file))) {
      const size = call[0].match(/size="(\d+)"/)
      if (size) {
        assert.ok([14, 16, 18, 20].includes(Number(size[1])),
          `size fora da escala em ${file}: ${size[1]}`)
        continue
      }
      if (/\bname="/.test(call[0]) && file !== 'App.vue') {
        assert.equal(file, 'App.vue', `AppIcon sem size em ${file}: ${call[0]}`)
      }
    }
  }
})

test('AppIcon com traço 2 e nenhum path manual fora do logo', () => {
  const brandStart = appIcon.indexOf('v-else-if="isBrand"')
  const tplStart = appIcon.indexOf('<template>')
  assert.match(appIcon, /stroke-width="2"/)
  const pathsBeforeBrand = appIcon
    .slice(tplStart, brandStart)
    .match(/<path\b/g)
  assert.equal(pathsBeforeBrand, null, 'path manual fora da marca smartboard')
})

test('somente AppIcon.vue importa lucide-vue-next', () => {
  const importers = vueFiles().filter((file) =>
    sourceOf(file).includes('lucide-vue-next'))
  assert.deepEqual(importers, ['components/AppIcon.vue'])
})

test('versão exata 1.0.0 em package.json e lockfile', () => {
  const pkg = readFileSync(join(here, '..', 'package.json'), 'utf8')
  assert.match(pkg, /"lucide-vue-next": "1\.0\.0"/)
  const lock = readFileSync(join(here, '..', 'package-lock.json'), 'utf8')
  assert.match(lock, /"node_modules\/lucide-vue-next": \{\s*\n\s*"version": "1\.0\.0"/)
  assert.match(lock, /"lucide-vue-next": "1\.0\.0"/)
})

test('todo botão só de ícone tem aria-label', () => {
  for (const file of vueFiles()) {
    for (const body of iconOnlyButtons(sourceOf(file))) {
      const tag = body.slice(0, body.indexOf('>'))
      assert.match(tag, /aria-label=/, `botão só de ícone sem aria-label (${file})`)
    }
  }
})

test('title Edit usa edit, Delete/Remove usa delete, Save/Create usa check', () => {
  for (const file of vueFiles()) {
    for (const body of buttonBodies(sourceOf(file))) {
      const tag = body.slice(0, body.indexOf('>'))
      const inner = body.slice(body.indexOf('>') + 1)
      if (/title="Edit"/.test(tag)) assert.match(inner, /name="edit"/)
      if (/title="(?:Delete|Remove)"/.test(tag)) {
        assert.match(inner, /name="delete"/)
        assert.doesNotMatch(inner, /name="close"/)
      }
      if (/title="(?:Save|Create)"/.test(tag)) assert.match(inner, /name="check"/)
    }
  }
})
