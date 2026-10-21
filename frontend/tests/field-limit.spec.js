import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FieldLimit from '../src/components/FieldLimit.vue'

const mountLimit = (text, limit = 1023, props = {}) =>
  mount(FieldLimit, {
    props: { id: 'test-limit', text, limit, field: 'comment', ...props },
  })

describe('FieldLimit', () => {
  it('exibe contador de caracteres para o campo', () => {
    const wrapper = mountLimit('Test text')

    expect(wrapper.find('.field-limit-count').text()).toBe('9/1023')
  })

  it('conta code points de emoji, não unidades UTF-16', () => {
    const wrapper = mountLimit('a\u{1F680}b')

    expect(wrapper.find('.field-limit-count').text()).toBe('3/1023')
  })

  it('mostra warning a partir de 90% do limite', () => {
    const nearThreshold = mountLimit('a'.repeat(921))
    const beforeThreshold = mountLimit('a'.repeat(920))

    expect(nearThreshold.find('.field-limit-warning').exists()).toBe(true)
    expect(beforeThreshold.find('.field-limit-warning').exists()).toBe(false)
  })

  it('mantém o warning no limite exato', () => {
    const wrapper = mountLimit('a'.repeat(1023))

    expect(wrapper.find('.field-limit-warning').exists()).toBe(true)
  })

  it('mostra erro calculado quando excede o limite', () => {
    const wrapper = mountLimit('a'.repeat(1024))

    expect(wrapper.find('.field-limit-error').text()).toContain('1023')
    expect(wrapper.find('.field-limit-error').text()).toContain('1024')
  })

  it('exibe o erro vindo do prop mesmo abaixo do limite', () => {
    const wrapper = mountLimit('ok', 1023, { error: 'Falha registrada pelo servidor' })

    expect(wrapper.find('.field-limit-error').text()).toBe('Falha registrada pelo servidor')
    expect(wrapper.find('.field-limit-warning').exists()).toBe(false)
  })

  it('expõe role="status" na mensagem de erro', () => {
    const wrapper = mountLimit('a'.repeat(1024))

    expect(wrapper.find('.field-limit-error').attributes('role')).toBe('status')
  })

  it('marca o contêiner com a classe over acima do limite', () => {
    const wrapper = mountLimit('a'.repeat(1024))

    expect(wrapper.find('.field-limit').classes()).toContain('over')
  })
})
