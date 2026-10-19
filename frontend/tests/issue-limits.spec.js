import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import IssueLimits from '../src/components/IssueLimits.vue'

// Mockando a API
vi.mock('../src/api/index.js', () => ({
  api: {
    // Mock para testes
  }
}))

// Mockando AppIcon
vi.mock('../src/components/AppIcon.vue', () => ({
  default: {
    name: 'AppIcon',
    template: '<span>Icon</span>'
  }
}))

describe('IssueLimits', () => {
  beforeEach(() => {
    // Resetando os mocks antes de cada teste
    vi.clearAllMocks()
  })

  it('exibe contador de caracteres para descrição', () => {
    const wrapper = mount(IssueLimits, {
      props: {
        value: 'Texto de teste',
        limit: 4095,
        type: 'description'
      }
    })
    
    expect(wrapper.find('[data-testid="description-counter"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('14/4095')
  })

  it('exibe contador de caracteres para comentário', () => {
    const wrapper = mount(IssueLimits, {
      props: {
        value: 'Texto de teste',
        limit: 1023,
        type: 'comment'
      }
    })
    
    expect(wrapper.find('[data-testid="comment-counter"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('14/1023')
  })

  it('mostra warning ao atingir 90% do limite para descrição', () => {
    const text90Percent = 'a'.repeat(3685) // 90% de 4095
    
    const wrapper = mount(IssueLimits, {
      props: {
        value: text90Percent,
        limit: 4095,
        type: 'description'
      }
    })
    
    expect(wrapper.find('[data-testid="description-warning"]').exists()).toBe(true)
  })

  it('mostra warning ao atingir 90% do limite para comentário', () => {
    const text90Percent = 'a'.repeat(920) // 90% de 1023
    
    const wrapper = mount(IssueLimits, {
      props: {
        value: text90Percent,
        limit: 1023,
        type: 'comment'
      }
    })
    
    expect(wrapper.find('[data-testid="comment-warning"]').exists()).toBe(true)
  })

  it('mostra erro ao exceder o limite para descrição', () => {
    const textExceed = 'a'.repeat(4096) // excedendo o limite
    
    const wrapper = mount(IssueLimits, {
      props: {
        value: textExceed,
        limit: 4095,
        type: 'description'
      }
    })
    
    expect(wrapper.find('[data-testid="description-error"]').exists()).toBe(true)
  })

  it('mostra erro ao exceder o limite para comentário', () => {
    const textExceed = 'a'.repeat(1024) // excedendo o limite
    
    const wrapper = mount(IssueLimits, {
      props: {
        value: textExceed,
        limit: 1023,
        type: 'comment'
      }
    })
    
    expect(wrapper.find('[data-testid="comment-error"]').exists()).toBe(true)
  })

  it('bloqueia comentário ao exceder limite', () => {
    const wrapper = mount(IssueLimits, {
      props: {
        value: 'a'.repeat(1024), // excedendo o limite
        limit: 1023,
        type: 'comment'
      }
    })
    
    const button = wrapper.find('[data-testid="comment-submit"]')
    expect(button.attributes('disabled')).toBe('')
  })

  it('permite edição e salvar/cancelar', () => {
    const wrapper = mount(IssueLimits, {
      props: {
        value: 'Texto inicial',
        limit: 4095,
        type: 'description'
      }
    })
    
    // Testando interação de edição
    expect(wrapper.find('[data-testid="edit-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="save-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="cancel-button"]').exists()).toBe(true)
  })

  it('atualiza ARIA quando o limite é atingido', () => {
    const wrapper = mount(IssueLimits, {
      props: {
        value: 'Texto de teste',
        limit: 4095,
        type: 'description'
      }
    })
    
    const counter = wrapper.find('[data-testid="description-counter"]')
    expect(counter.attributes('aria-valuenow')).toBe('14')
    expect(counter.attributes('aria-valuemax')).toBe('4095')
  })
})