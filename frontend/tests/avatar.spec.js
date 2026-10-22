import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Avatar from '../src/components/Avatar.vue'

describe('Avatar', () => {
  it('aplica classes sm/md/lg e ring corretamente', () => {
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        size: 'sm',
        ring: true
      }
    })
    
    const classes = wrapper.classes()
    expect(classes).toContain('avatar')
    expect(classes).toContain('sm')
    expect(classes).toContain('ring')
    
    // Teste sem ring (padrão)
    const wrapper2 = mount(Avatar, {
      props: {
        name: 'Vivy',
        size: 'md'
      }
    })
    
    const classes2 = wrapper2.classes()
    expect(classes2).toContain('avatar')
    expect(classes2).toContain('md')
    expect(classes2).not.toContain('ring')
  })

  it('exibe a inicial maiúscula e ? para nome vazio', () => {
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: ''
      }
    })
    
    expect(wrapper.text()).toBe('V')
    
    const wrapper2 = mount(Avatar, {
      props: {
        name: '',
        image: ''
      }
    })
    
    expect(wrapper2.text()).toBe('?')
  })

  it('define aria-label corretamente', () => {
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        decorative: false
      }
    })
    
    expect(wrapper.attributes('role')).toBe('img')
    expect(wrapper.attributes('aria-label')).toBe('Avatar de Vivy')
    
    const wrapper2 = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        decorative: true
      }
    })
    
    expect(wrapper2.attributes('aria-hidden')).toBe('true')
    expect(wrapper2.attributes('role')).toBeUndefined()
    expect(wrapper2.attributes('aria-label')).toBeUndefined()
  })

  it('cai no fallback quando a imagem falha', async () => {
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
      }
    })
    
    // O avatar tem a imagem, então o texto deve ser a inicial V
    expect(wrapper.find('img').exists()).toBe(true)
    
    // Simula erro de carregamento
    await wrapper.find('img').trigger('error')
    
    // Verifica que o fallback foi ativado - deve mostrar a inicial
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toBe('V')
  })

  it('exibe img com src quando imagem é fornecida', () => {
    const imageSrc = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: imageSrc
      }
    })
    
    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('img').attributes('src')).toBe(imageSrc)
  })

  it('reseta fallback ao trocar image', async () => {
    const imageSrc = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
    const wrapper = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: imageSrc
      }
    })
    
    // Primeiro tem imagem
    expect(wrapper.find('img').exists()).toBe(true)
    
    // Muda a imagem para uma imagem vazia (deve mostrar o fallback)
    await wrapper.setProps({ image: '' })
    
    // Deve agora mostrar a inicial porque a imagem foi apagada
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toBe('V')
  })

  it('calcula contraste adequado para cores claras, escuras e médias', () => {
    // Cor clara (contraste alto com preto)
    const wrapper1 = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        color: '#ffffff'
      }
    })
    
    // Necessário usar o método computado diretamente
    expect(wrapper1.vm.readableInk('#ffffff')).toBe('#000000')
    
    // Cor escura (contraste alto com branco)
    const wrapper2 = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        color: '#000000'
      }
    })
    expect(wrapper2.vm.readableInk('#000000')).toBe('#ffffff')
    
    // Cor média (limiar)
    const wrapper3 = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        color: '#8a7249'
      }
    })
    expect(wrapper3.vm.readableInk('#8a7249')).toBe('#000000') // Deve ser preto para essa cor (acima do threshold)
    
    // Verificação adicional: contraste adequado (não deve ser menor que 4.5:1)
    // Vamos testar com uma cor onde o contraste é exatamente 4.5:1
    const wrapper4 = mount(Avatar, {
      props: {
        name: 'Vivy',
        image: '',
        color: '#505050'
      }
    })
    // Esta cor testa um caso onde o contraste está no limite
    expect(wrapper4.vm.readableInk('#505050')).toBe('#ffffff') 
  })
})