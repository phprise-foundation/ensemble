import { test } from 'node:test'
import { equal, match, ok } from 'node:assert'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

// Ler o SCSS
const mainScss = fs.readFileSync(path.join(__dirname, '../src/styles/main.scss'), 'utf8')

// Verificar CSS do avatar
test('Avatar: .avatar tem border-radius 50%', () => {
  match(mainScss, /\.avatar[\s\S]*?border-radius:\s*50%/)
})

test('Avatar: .avatar img tem object-fit: cover', () => {
  match(mainScss, /\.avatar img[\s\S]*?object-fit:\s*cover/)
})

test('Avatar: .avatar img tem object-position: center', () => {
  match(mainScss, /\.avatar img[\s\S]*?object-position:\s*center/)
})

test('Avatar: tamanhos 22/42/64 definidos', () => {
  match(mainScss, /\.avatar\.sm[\s\S]*?width:\s*22px/)
  match(mainScss, /\.avatar\.md[\s\S]*?width:\s*42px/)
  match(mainScss, /\.avatar\.lg[\s\S]*?width:\s*64px/)
})

// Ler o seed
const seedJs = fs.readFileSync(path.join(__dirname, '../src/data/seed.js'), 'utf8')

test('Avatar: seed.js contém portrait de Vivy', () => {
  match(seedJs, /portrait:\s*VIVY_PORTRAIT/)
})

// Ler seed-portrait.js
const seedPortraitJs = fs.readFileSync(path.join(__dirname, '../src/data/seed-portrait.js'), 'utf8')

test('Avatar: seed-portrait.js contém data URL PNG', () => {
  match(seedPortraitJs, /export.*VIVY_PORTRAIT.*data:image\/png/)
})

// Verificar package.json
const packageJson = JSON.parse(fs.readFileSync(path.join(__dirname, '../package.json'), 'utf8'))

test('Avatar: nenhuma dependência nova', () => {
  // Lista de dependências esperadas (exatamente igual ao package.json)
  const expectedDeps = [
    'vue',
    'vue-router',
    'lucide-vue-next',
  ]
  
  const expectedDevDeps = [
    '@vitejs/plugin-vue',
    '@vue/test-utils',
    'cypress',
    'jsdom',
    'sass',
    'vite',
    'vitest',
  ]
  
  // Verificar que não há novas dependências
  const actualDeps = Object.keys(packageJson.dependencies || {})
  const actualDevDeps = Object.keys(packageJson.devDependencies || {})
  
  const allActual = [...actualDeps, ...actualDevDeps]
  const allExpected = [...expectedDeps, ...expectedDevDeps]
  
  // Para evitar falsos positivos, vamos checar se há dependências novas
  const newDeps = allActual.filter(dep => !allExpected.includes(dep))
  const missingDeps = allExpected.filter(dep => !allActual.includes(dep))
  
  equal(newDeps.length, 0, `Novas dependências detectadas: ${newDeps.join(', ')}`)
  equal(missingDeps.length, 0, `Dependências esperadas faltando: ${missingDeps.join(', ')}`)
})