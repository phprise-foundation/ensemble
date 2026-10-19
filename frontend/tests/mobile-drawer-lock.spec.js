import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import App from '../src/App.vue'

vi.mock('../src/store/board.js', () => ({
  boardStore: {
    load: () => {},
    workspace: { value: undefined },
    boardsOf: () => [],
    workflowsOf: () => [],
    teamsOf: () => [],
    sourcesOf: () => [],
  },
}))
vi.mock('../src/store/session.js', () => ({ session: { signedIn: true, load: () => {} } }))
vi.mock('../src/api/index.js', () => ({ hasSimulation: { value: false }, setAll: () => {} }))
vi.mock('../src/theme.js', () => ({ theme: { mode: 'dark' }, toggleTheme: () => {} }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ meta: { title: 'Test' } }),
  useRouter: () => ({ afterEach: () => {} }),
}))

function setupMatchMedia(maxWidths) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query) => {
      const limit = Number.parseInt(/max-width:\s*(\d+)px/.exec(query)?.[1] ?? '0', 10)
      return {
        matches: maxWidths.some((width) => width <= limit),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      }
    }),
  })
}

async function mountApp(width) {
  setupMatchMedia([width])
  const wrapper = mount(App, {
    global: {
      stubs: {
        RouterView: true,
        RouterLink: 'a',
        AppIcon: 'span',
        AppCopilot: true,
        AppConsole: true,
      },
    },
    attachTo: document.body,
  })
  await nextTick()
  return wrapper
}

describe('App travamento do body pelo drawer', () => {
  beforeEach(() => {
    document.documentElement.style.setProperty('--breakpoint-sidebar', '1024px')
    document.documentElement.style.setProperty('--breakpoint-mobile', '768px')
    document.body.classList.remove('drawer-locked')
  })

  it('trava o body abaixo de 768px e destrava ao fechar', async () => {
    const wrapper = await mountApp(767)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(document.body.classList.contains('drawer-locked')).toBe(true)

    await wrapper.find('.scrim').trigger('click')
    await nextTick()
    expect(document.body.classList.contains('drawer-locked')).toBe(false)
    wrapper.unmount()
  })

  it('não trava o body na faixa parcial de 768px a 1024px', async () => {
    const wrapper = await mountApp(1000)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(document.body.classList.contains('drawer-locked')).toBe(false)

    expect(wrapper.findComponent({ name: 'AppSidebar' }).props('narrow')).toBe(false)
    wrapper.unmount()
  })

  it('passa narrow abaixo de 768px e remove o lock no unmount', async () => {
    const wrapper = await mountApp(767)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(wrapper.findComponent({ name: 'AppSidebar' }).props('narrow')).toBe(true)

    wrapper.unmount()
    expect(document.body.classList.contains('drawer-locked')).toBe(false)
  })
})
