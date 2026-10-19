import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FieldLimit from '../src/components/FieldLimit.vue'

// Mockando a API
vi.mock('../src/api/index.js', () => ({
  api: {
    // Mock para testes
  }
}))

describe('FieldLimit', () => {
  it('exibe contador de caracteres para descrição', () => {
    const wrapper = mount(FieldLimit, {
      props: {
        modelValue: 'Texto de teste',
        limit: 4095
      }
    })
    
    expect(wrapper.find('[data-testid="character-count"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('14/4095')
  })

  it('exibe contador de caracteres para comentário', () => {
    const wrapper = mount(FieldLimit, {
      props: {
        modelValue: 'Texto de teste',
        limit: 1023
      }
    })
    
    expect(wrapper.find('[data-testid="character-count"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('14/1023')
  })

  it('mostra warning quando atinge 90% do limite', () => {
    const text90Percent = 'a'.repeat(3685) // 90% de 4095
    
    const wrapper = mount(FieldLimit, {
      props: {
        modelValue: text90Percent,
        limit: 4095
      }
    })
    
    expect(wrapper.find('[data-testid="warning"]').exists()).toBe(true)
  })

  it('mostra erro quando excede o limite', () => {
    const textExceed = 'a'.repeat(4096) // excedendo o limite
    
    const wrapper = mount(FieldLimit, {
      props: {
        modelValue: textExceed,
        limit: 4095
      }
    })
    
    expect(wrapper.find('[data-testid="error"]').exists()).toBe(true)
  })

  it('atualiza ARIA quando o limite é atingido', () => {
    const wrapper = mount(FieldLimit, {
      props: {
        modelValue: 'Texto de teste',
        limit: 4095
      }
    })
    
    const element = wrapper.find('[data-testid="character-count"]')
    expect(element.attributes('aria-valuenow')).toBe('14')
    expect(element.attributes('aria-valuemax')).toBe('4095')
  })
})