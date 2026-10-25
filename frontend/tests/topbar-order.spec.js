import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import App from '../src/App.vue'

vi.mock('../src/store/board.js', () => ({
  boardStore: {
    load: vi.fn(),
    workspace: { value: { id: 'ws', name: 'Workspace' } },
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

const stubs = vi.hoisted(() => ({
  setAll: vi.fn(),
  toggleTheme: vi.fn(),
  hasSimulation: { value: true },
}))

vi.mock('../src/api/index.js', async () => {
  const { computed, ref } = await import('vue')
  const simulationRef = ref(true)
  stubs.hasSimulation = simulationRef
  return {
    ...(await vi.importActual('../src/api/index.js')),
    isSimulated: () => false,
    setFeature: () => {},
    setAll: stubs.setAll,
    hasSimulation: computed(() => simulationRef.value),
    simulatedFeatures: { value: [] },
    simulation: { features: {} },
    api: {
      me: vi.fn(),
      workspaces: vi.fn(),
    },
  }
})

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
  useRoute: () => ({ meta: { title: 'Titulo de teste' } }),
  useRouter: () => ({ afterEach: () => {}, push: vi.fn() }),
}))

vi.mock('../src/theme.js', () => ({
  theme: { mode: 'dark' },
  toggleTheme: stubs.toggleTheme,
}))

function setupMatchMedia(matches) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation(() => ({
      matches,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
}

const ConsoleStub = {
  name: 'AppConsole',
  template: '<section id="app-console" class="console-panel-stub"></section>',
}

const CopilotStub = {
  name: 'AppCopilot',
  template: '<section id="app-copilot" class="copilot-panel-stub"></section>',
}

const SidebarStub = {
  name: 'AppSidebar',
  props: { open: Boolean, isMobile: Boolean, collapsed: Boolean },
  template: '<aside id="app-sidebar" class="sidebar-stub"></aside>',
}

async function mountApp(mobile) {
  setupMatchMedia(mobile)
  const wrapper = mount(App, {
    global: {
      stubs: {
        RouterView: true,
        RouterLink: 'a',
        AppIcon: 'span',
        AppSidebar: SidebarStub,
        AppCopilot: CopilotStub,
        AppConsole: ConsoleStub,
      },
    },
    attachTo: document.body,
  })
  await nextTick()
  await flushPromises()
  return wrapper
}

function slots(wrapper) {
  return wrapper.findAll('.topbar-actions [data-topbar-slot]').map((node) => node.attributes('data-topbar-slot'))
}

describe('Ordem dos controles do topbar', () => {
  beforeEach(() => {
    stubs.hasSimulation.value = true
    stubs.setAll.mockClear()
    stubs.toggleTheme.mockClear()
    document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  })

  it('em desktop, os slots seguem a ordem canonica e o perfil e o ultimo', async () => {
    const wrapper = await mountApp(false)

    expect(slots(wrapper)).toEqual(['grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile'])
    expect(wrapper.find('.topbar .spacer').element.nextElementSibling.className).toContain('topbar-actions')
    expect(wrapper.findAll('.topbar-actions > *').at(-1).attributes('data-topbar-slot')).toBe('profile')
    wrapper.unmount()
  })

  it('em mobile, nenhuma grade e renderizada e menu, Sim, tema e perfil permanecem', async () => {
    const wrapper = await mountApp(true)

    expect(slots(wrapper)).toEqual(['theme', 'profile'])
    expect(wrapper.find('.menu-toggle').exists()).toBe(true)
    for (const control of ['.sidebar-toggle', '.console-toggle', '.copilot-toggle']) {
      expect(wrapper.find(control).exists()).toBe(false)
    }
    wrapper.unmount()
  })


  it('cada grade alterna seu painel e o aria-expanded acompanha', async () => {
    const wrapper = await mountApp(false)

    const sidebar = wrapper.find('[data-topbar-slot="grid-left"]')
    expect(sidebar.attributes('aria-expanded')).toBe('true')
    await sidebar.trigger('click')
    expect(sidebar.attributes('aria-expanded')).toBe('false')

    const consoleToggle = wrapper.find('[data-topbar-slot="grid-bottom"]')
    expect(consoleToggle.attributes('aria-expanded')).toBe('false')
    await consoleToggle.trigger('click')
    expect(wrapper.find('#app-console').exists()).toBe(true)
    expect(consoleToggle.attributes('aria-expanded')).toBe('true')

    const copilot = wrapper.find('[data-topbar-slot="grid-right"]')
    await copilot.trigger('click')
    expect(wrapper.find('#app-copilot').exists()).toBe(false)
    expect(copilot.attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('tema alterna e cada controle tem rotulo acessivel', async () => {
    const wrapper = await mountApp(false)

    await wrapper.find('[data-topbar-slot="theme"]').trigger('click')
    expect(stubs.toggleTheme).toHaveBeenCalledTimes(1)
    for (const node of wrapper.findAll('.topbar-actions [data-topbar-slot]')) {
      expect(node.attributes('aria-label') || node.html()).toBeTruthy()
    }
    expect(wrapper.find('[data-topbar-slot="theme"]').attributes('aria-label')).toBe('Light theme')
    wrapper.unmount()
  })
})
