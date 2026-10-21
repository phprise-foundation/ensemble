import assert from 'node:assert/strict'
import { test } from 'node:test'

import { fitWithin, IMAGE_MAX_SIDE, IMAGE_QUALITY } from '../src/lib/image-normalize.js'
import { avatarColorFor } from '../src/lib/avatar-color.js'

const PALETA = ['#4c9aff', '#00b8d9', '#8777d9', '#ff991f', '#e5484d', '#46a758', '#c95dd8', '#8a7249']

test('exporta as constantes de normalização', () => {
  assert.equal(IMAGE_MAX_SIDE, 512)
  assert.equal(IMAGE_QUALITY, 0.85)
})

test('fitWithin mantém as dimensões quando os dois lados cabem no máximo', () => {
  assert.deepEqual(fitWithin(300, 200, 512), { width: 300, height: 200 })
  assert.deepEqual(fitWithin(512, 512, 512), { width: 512, height: 512 })
})

test('fitWithin limita imagem paisagem pela largura, preservando a proporção', () => {
  const dimensoes = fitWithin(1024, 768, 512)
  assert.equal(dimensoes.width, 512)
  assert.equal(dimensoes.height, 384)
})

test('fitWithin limita imagem retrato pela altura, preservando a proporção', () => {
  const dimensoes = fitWithin(768, 1024, 512)
  assert.equal(dimensoes.width, 384)
  assert.equal(dimensoes.height, 512)
})

test('fitWithin arredonda as dimensões para cima', () => {
  const dimensoes = fitWithin(1000, 333, 512)
  assert.equal(dimensoes.width, 512)
  assert.equal(dimensoes.height, 171)
  assert.ok(Number.isInteger(dimensoes.height))
})

test('avatarColorFor é determinístico: o mesmo login gera a mesma cor', () => {
  assert.equal(avatarColorFor('admin'), avatarColorFor('admin'))
  assert.equal(avatarColorFor('Alice'), avatarColorFor('Alice'))
})

test('avatarColorFor sempre devolve uma cor da paleta fixa', () => {
  for (const login of ['a', 'b', 'admin', 'carol', 'Dave_123', '长名字', 'user_with_long_name']) {
    const cor = avatarColorFor(login)
    assert.equal(typeof cor, 'string')
    assert.ok(cor.startsWith('#'))
    assert.ok(PALETA.includes(cor))
  }
})

test('o login é hasheado por inteiro (comportamento FNV-1a)', () => {
  assert.notEqual(avatarColorFor('a'), avatarColorFor('ab'))
  assert.notEqual(avatarColorFor('ab'), avatarColorFor('ba'))
})
