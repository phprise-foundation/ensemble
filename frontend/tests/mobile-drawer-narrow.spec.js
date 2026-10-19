import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import AppSidebar from '../src/components/AppSidebar.vue'

vi.mock('vue-router', () => ({
  useRoute: () => ({ meta: { title: 'Test' } }),
  useRouter: () => ({ afterEach: () => {} }),
}))

vi.mock('../src/api/index.js', () => ({
  isSimulated: () => false,
}))

function mountSidebar(props) {
  return mount(AppSidebar, {
    props: { open: true, isMobile: true, ...props },
    global: {
      stubs: {
        AppIcon: 'span',
        RouterLink: 'a',
      },
    },
    attachTo: document.body,
  })
}

describe('AppSidebar botão de fechar em tela cheia', () => {
  it('mostra o botão de fechar com rótulo acessível quando narrow e mobile', async () => {
    const wrapper = mountSidebar({ narrow: true })
    await nextTick()

    const close = wrapper.find('button.sidebar-close')
    expect(close.exists()).toBe(true)
    expect(close.attributes('aria-label')).toBe('Fechar menu')
    expect(close.attributes('title')).toBe('Fechar menu')
    expect(close.attributes('type')).toBe('button')

    await close.trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('não mostra o botão de fechar na faixa parcial', async () => {
    const wrapper = mountSidebar({ narrow: false })
    await nextTick()

    expect(wrapper.find('button.sidebar-close').exists()).toBe(false)
    wrapper.unmount()
  })
})
