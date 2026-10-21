import assert from 'node:assert/strict'
import { test } from 'node:test'

import { fitWithin, IMAGE_MAX_SIDE, IMAGE_QUALITY } from '../src/lib/image-normalize.js'
import { avatarColorFor } from '../src/lib/avatar-color.js'

test('exports correct constants', () => {
  assert.equal(IMAGE_MAX_SIDE, 512)
  assert.equal(IMAGE_QUALITY, 0.85)
})

test('fitWithin returns original dimensions when both sides <= maxSide', () => {
  assert.deepEqual(fitWithin(300, 200, 512), { width: 300, height: 200 })
  assert.deepEqual(fitWithin(512, 512, 512), { width: 512, height: 512 })
})

test('fitWithin caps landscape image by width, preserving ratio', () => {
  const r = fitWithin(1024, 768, 512)
  assert.equal(r.width, 512)
  assert.equal(r.height, 384)
})

test('fitWithin caps portrait image by height, preserving ratio', () => {
  const r = fitWithin(768, 1024, 512)
  assert.equal(r.width, 384)
  assert.equal(r.height, 512)
})

test('fitWithin rounds dimensions up (ceil)', () => {
  const r = fitWithin(1000, 333, 512)
  assert.equal(r.width, 512)
  assert.equal(r.height, 171)
  assert.ok(Number.isInteger(r.height))
})

test('avatarColorFor is deterministic (same login -> same color)', () => {
  assert.equal(avatarColorFor('admin'), avatarColorFor('admin'))
  assert.equal(avatarColorFor('Alice'), avatarColorFor('Alice'))
})

test('avatarColorFor always returns a color from the fixed palette', () => {
  const palette = ['#4c9aff', '#00b8d9', '#8777d9', '#ff991f', '#e5484d', '#46a758', '#c95dd8', '#8a7249']
  for (const login of ['a', 'b', 'admin', 'carol', 'Dave_123', '长名字', 'user_with_long_name']) {
    const color = avatarColorFor(login)
    assert.equal(typeof color, 'string')
    assert.ok(color.startsWith('#'))
    assert.ok(palette.includes(color))
  }
})

test('login is hashed over its full length (FNV-1a behavior)', () => {
  assert.notEqual(avatarColorFor('a'), avatarColorFor('ab'))
  assert.notEqual(avatarColorFor('ab'), avatarColorFor('ba'))
})
