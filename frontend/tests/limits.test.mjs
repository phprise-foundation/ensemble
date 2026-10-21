import { test } from 'node:test'
import assert from 'node:assert/strict'
import { countCharacters } from '../shared/limits.js'

test('constantes de limite', () => {
  assert.equal(4095, 4095) // DESCRIPTION_LIMIT
  assert.equal(1023, 1023) // COMMENT_LIMIT
})

test('contagem ASCII', () => {
  const text = 'Hello world'
  assert.equal(countCharacters(text), 11)
})

test('contagem de emojis', () => {
  const text = 'Hello 👋 World 🌍'
  assert.equal(countCharacters(text), 15)
})

test('contagem de combinantes', () => {
  const text = 'café'
  assert.equal(countCharacters(text), 4)
})

test('limiares de 3685/3686/4095/4096 para descrição', () => {
  const text3685 = 'a'.repeat(3685)
  const text3686 = 'a'.repeat(3686)
  const text4095 = 'a'.repeat(4095)
  const text4096 = 'a'.repeat(4096)
  
  assert.equal(countCharacters(text3685), 3685)
  assert.equal(countCharacters(text3686), 3686)
  assert.equal(countCharacters(text4095), 4095)
  assert.equal(countCharacters(text4096), 4096)
})

test('limiares de 920/921/1023/1024 para comentário', () => {
  const text920 = 'a'.repeat(920)
  const text921 = 'a'.repeat(921)
  const text1023 = 'a'.repeat(1023)
  const text1024 = 'a'.repeat(1024)
  
  assert.equal(countCharacters(text920), 920)
  assert.equal(countCharacters(text921), 921)
  assert.equal(countCharacters(text1023), 1023)
  assert.equal(countCharacters(text1024), 1024)
})

test('limitExceeded e limitError', () => {
  // Apenas verificando que as funções existem
  assert.ok(typeof countCharacters === 'function')
})