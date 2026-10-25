import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import App from '../src/App.vue'
import { session } from '../src/store/session.js'

vi.mock('../src/store/board.js', () => ({
  boardStore: {
    load: vi.fn(),
    workspace: { value: { id: 'ws', name: 'Workspace' } },
    board: { value: null },
    boardsOf: () => [],
    workflowsOf: () => [],
    teamsOf: () => [],
    sourcesOf: () => [],
    statusesOf: () => [],
    transitionsOf: () => [],
    issueTypesOf: () => [],
    agentsOf: () => [],
    rolesOf: () => [],
    profilesOf: () => [],
  },
}))

vi.mock('../src/api/index.js', () => ({
  isSimulated: () => false,
  setFeature: () => {},
  setAll: () => {},
  hasSimulation: { value: false },
  simulatedFeatures: { value: [] },
  simulation: { features: {} },
  api: {
    me: vi.fn(),
    workspaces: vi.fn(),
  },
}))

const client = vi.hoisted(() => ({ tokenValue: 't', request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  token: () => client.tokenValue,
  setToken: (value) => {
    client.tokenValue = value
  },
  request: (...args) => client.request(...args),
}))


client.request.mockImplementation(async (method, path) => {
  if (path === '/api/me') {
    return { user: { login: 'admin', displayName: 'Admin' } }
  }
  if (path === '/api/workspaces') {
    return { workspaces: [{ id: 'ws', name: 'Workspace' }] }
  }
  if (path === '/api/auth/login') {
    return { token: 't', user: { login: 'admin', displayName: 'Admin' } }
  }
  throw new Error(`unexpected ${method} ${path}`)
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ meta: { title: 'Test' } }),
  useRouter: () => ({ afterEach: () => {} }),
}))

vi.mock('../src/theme.js', () => ({
  theme: { mode: 'dark' },
  toggleTheme: () => {},
  CONSOLE_HEIGHT_STEP: 1,
  DEFAULT_CONSOLE_HEIGHT: 1,
}))
vi.mock('../src/store/consoleHeight.js', () => ({
  CONSOLE_HEIGHT_STEP: 1,
  DEFAULT_CONSOLE_HEIGHT: 1,
  clampHeight: (value) => value,
}))

const sidebarStub = {
  name: 'AppSidebar',
  props: { open: Boolean, isMobile: Boolean, narrow: Boolean, collapsed: Boolean },
  template: `<aside><button class="sidebar-close" type="button" @click="$emit('close')">x</button></aside>`,
}

function setupViewports(viewport) {
  document.documentElement.style.setProperty('--breakpoint-sidebar', '1024px')
  document.documentElement.style.setProperty('--breakpoint-mobile', '768px')
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query) => ({
      matches: query.includes('767px') ? viewport.narrow : viewport.mobile,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
}

async function mountApp(stubSidebar) {
  await session.signIn('admin', 'admin1234')
  const stubs = { RouterView: true, RouterLink: 'a', AppIcon: 'span', AppCopilot: true, AppConsole: true }
  if (stubSidebar) {
    stubs.AppSidebar = sidebarStub
  }
  const wrapper = mount(App, {
    global: { stubs },
    attachTo: document.body,
  })
  await nextTick()
  await flushPromises()
  return wrapper
}

function bodyHasClass(name) {
  return document.body.classList.contains(name)
}

describe('App drawer em tela cheia', () => {
  it('trava o body e expõe o botão de fechar abaixo de 768px', async () => {
    setupViewports({ mobile: true, narrow: true })
    const wrapper = await mountApp(false)
    expect(document.body.style.overflow).toBe('')
    expect(bodyHasClass('drawer-locked')).toBe(false)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(bodyHasClass('drawer-locked')).toBe(true)
    expect(wrapper.find('.content').attributes('inert')).toBe('true')

    await wrapper.find('.sidebar-close').trigger('click')
    await nextTick()
    expect(wrapper.find('.content').attributes('inert')).toBeUndefined()
    expect(bodyHasClass('drawer-locked')).toBe(false)
    wrapper.unmount()
  })

  it('não trava o body nem mostra o botão de fechar na faixa parcial de 768px a 1024px', async () => {
    setupViewports({ mobile: true, narrow: false })
    const wrapper = await mountApp(false)
    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(wrapper.find('.scrim').exists()).toBe(true)
    expect(wrapper.find('.sidebar-close').exists()).toBe(false)
    expect(document.body.classList.contains('drawer-locked')).toBe(false)
    wrapper.unmount()
    expect(bodyHasClass('drawer-locked')).toBe(false)
  })

  it('mantém o desktop livre de lock e de botão dedicado', async () => {
    setupViewports({ mobile: false, narrow: false })
    const wrapper = await mountApp(true)
    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(bodyHasClass('drawer-locked')).toBe(false)
    wrapper.unmount()
  })

  it('remove a classe drawer-locked do body no unmount com o drawer aberto', async () => {
    setupViewports({ mobile: true, narrow: true })
    const wrapper = await mountApp(true)
    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(bodyHasClass('drawer-locked')).toBe(true)

    wrapper.unmount()
    expect(bodyHasClass('drawer-locked')).toBe(false)
  })

  it('só manipula a classe do body entre a abertura e o fecho na faixa estreita', async () => {
    setupViewports({ mobile: true, narrow: true })
    const wrapper = await mountApp(false)
    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()
    expect(wrapper.find('#app-sidebar').exists()).toBe(true)
    expect(wrapper.find('.content').attributes('inert')).toBe('true')

    await wrapper.find('.scrim').trigger('click')
    await nextTick()
    expect(bodyHasClass('drawer-locked')).toBe(false)
    wrapper.unmount()
  })
})
