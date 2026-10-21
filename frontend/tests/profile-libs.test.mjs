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

test('fitWithin nunca passa do teto e preserva a proporção nos dois sentidos', () => {
  assert.deepEqual(fitWithin(2048, 1024, 512), { width: 512, height: 256 })
  assert.deepEqual(fitWithin(1024, 2048, 512), { width: 256, height: 512 })
  assert.deepEqual(fitWithin(3000, 3000, 512), { width: 512, height: 512 })
  assert.deepEqual(fitWithin(200, 100, 512), { width: 200, height: 100 })
  assert.deepEqual(fitWithin(512, 300, 512), { width: 512, height: 300 })
})

test('fitWithin arredonda para cima o lado proporcional', () => {
  assert.deepEqual(fitWithin(1000, 683, 512), { width: 512, height: 350 })
  assert.deepEqual(fitWithin(683, 1000, 512), { width: 350, height: 512 })
  assert.deepEqual(fitWithin(1023, 2049, 512), { width: 256, height: 512 })
})

test('avatarColorFor dá a cor exata da paleta para logins conhecidos', () => {
  assert.equal(avatarColorFor('admin'), '#e5484d')
  assert.equal(avatarColorFor('alice'), '#8a7249')
  assert.equal(avatarColorFor('carol'), '#8777d9')
})

test('avatarColorFor é estável entre logins distintos: cada login tem sempre a mesma cor', () => {
  const logins = ['admin', 'alice', 'bob', 'carol', 'dave', 'User_42']
  const firstPass = logins.map((login) => avatarColorFor(login))
  const secondPass = logins.map((login) => avatarColorFor(login))
  assert.deepEqual(secondPass, firstPass, 'duas passadas dão as mesmas cores')
  for (const color of firstPass) {
    assert.ok(
      ['#4c9aff', '#00b8d9', '#8777d9', '#ff991f', '#e5484d', '#46a758', '#c95dd8', '#8a7249'].includes(color),
      `${color} vem da paleta fixa`,
    )
  }
})
