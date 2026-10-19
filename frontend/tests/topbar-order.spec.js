import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import App from '../src/App.vue'
import { session } from '../src/store/session.js'
import { hasSimulation, setAll } from '../src/api/index.js'
import { theme, toggleTheme } from '../src/theme.js'

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

vi.mock('../src/api/index.js', async () => {
  const { ref } = await import('vue')
  return {
    hasSimulation: ref(true),
    setAll: vi.fn(),
    isSimulated: () => true,
    setFeature: () => {},
    simulatedFeatures: ref([]),
    simulation: { features: {} },
    api: {
      fetchUser: vi.fn().mockResolvedValue({ login: 'admin', displayName: '' }),
      updateUser: vi.fn().mockResolvedValue({ login: 'admin', displayName: '' }),
    },
  }
})

vi.mock('../src/theme.js', async () => {
  const { reactive } = await import('vue')
  const state = reactive({ mode: 'dark' })
  return {
    theme: state,
    toggleTheme: vi.fn(() => {
      state.mode = state.mode === 'dark' ? 'light' : 'dark'
    }),
  }
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ meta: { title: 'Topbar' } }),
  useRouter: () => ({ push: vi.fn(), afterEach: () => {} }),
}))

const SidebarStub = {
  name: 'AppSidebar',
  props: { open: Boolean, isMobile: Boolean, collapsed: Boolean },
  template: '<aside id="app-sidebar" class="sidebar-stub" :class="{ collapsed }"></aside>',
}

const CopilotStub = {
  name: 'AppCopilot',
  template: '<section id="app-copilot" class="copilot-stub"></section>',
}

function setupMatchMedia(mobile) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation(() => ({
      matches: mobile,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
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
      },
    },
    attachTo: document.body,
  })
  await nextTick()
  return wrapper
}

function slotNames(wrapper) {
  return wrapper.findAll('[data-topbar-slot]').map((node) => node.attributes('data-topbar-slot'))
}

let wrapper

beforeEach(() => {
  document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  session.signIn()
  hasSimulation.value = true
  theme.mode = 'dark'
  setAll.mockClear()
  toggleTheme.mockClear()
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
  document.body.innerHTML = ''
})

describe('ordem dos controles do topbar', () => {
  it('em desktop mantém a ordem canônica da fase 2 dentro de .topbar-actions', async () => {
    wrapper = await mountApp(false)

    expect(wrapper.find('.topbar-actions').exists()).toBe(true)
    expect(slotNames(wrapper)).toEqual([
      'sim', 'grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile',
    ])
  })

  it('em mobile não renderiza nenhum controle de grade', async () => {
    wrapper = await mountApp(true)

    expect(slotNames(wrapper)).toEqual(['sim', 'theme', 'profile'])
    expect(wrapper.findAll('[data-topbar-slot^="grid-"]')).toHaveLength(0)
    expect(wrapper.find('.menu-toggle').exists()).toBe(true)
  })

  it('Sim alterna a simulação e mantém aria-pressed coerente', async () => {
    wrapper = await mountApp(false)

    const sim = wrapper.find('[data-topbar-slot="sim"]')
    expect(sim.element.tagName).toBe('BUTTON')
    expect(sim.attributes('aria-pressed')).toBe('true')
    expect(sim.text()).toContain('Sim')

    await sim.trigger('click')
    expect(setAll).toHaveBeenCalledWith(false)
    expect(sim.attributes('aria-pressed')).toBe('true')

    hasSimulation.value = false
    await nextTick()
    expect(sim.attributes('aria-pressed')).toBe('false')

    await sim.trigger('click')
    expect(setAll).toHaveBeenLastCalledWith(true)
  })

  it('grade esquerda alterna o sidebar e expõe aria-expanded', async () => {
    wrapper = await mountApp(false)

    const toggle = wrapper.find('[data-topbar-slot="grid-left"]')
    expect(toggle.attributes('aria-controls')).toBe('app-sidebar')
    expect(toggle.attributes('aria-expanded')).toBe('true')

    await toggle.trigger('click')
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(true)
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(false)
    expect(toggle.attributes('aria-expanded')).toBe('true')
  })

  it('grade direita alterna o copilot e expõe aria-expanded', async () => {
    wrapper = await mountApp(false)

    const toggle = wrapper.find('[data-topbar-slot="grid-right"]')
    expect(toggle.attributes('aria-controls')).toBe('app-copilot')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('#app-copilot').exists()).toBe(true)

    await toggle.trigger('click')
    expect(wrapper.find('#app-copilot').exists()).toBe(false)
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    expect(wrapper.find('#app-copilot').exists()).toBe(true)
    expect(toggle.attributes('aria-expanded')).toBe('true')
  })

  it('tema é o último controle antes do perfil e alterna o modo', async () => {
    wrapper = await mountApp(false)

    const slots = slotNames(wrapper)
    expect(slots.indexOf('theme')).toBe(slots.indexOf('profile') - 1)

    const toggle = wrapper.find('[data-topbar-slot="theme"]')
    expect(toggle.attributes('title')).toBe('Light theme')
    expect(toggle.attributes('aria-label')).toBe('Light theme')

    await toggle.trigger('click')
    expect(toggleTheme).toHaveBeenCalledTimes(1)
    expect(toggle.attributes('title')).toBe('Dark theme')
    expect(toggle.attributes('aria-label')).toBe('Dark theme')
  })

  it('perfil é o último slot e abre profile/logout', async () => {
    wrapper = await mountApp(false)

    expect(slotNames(wrapper).at(-1)).toBe('profile')

    const profile = wrapper.find('[data-topbar-slot="profile"]')
    expect(profile.classes()).toContain('profile-menu')

    await profile.find('.profile-trigger').trigger('click')
    const items = profile.findAll('.profile-dropdown [role="menuitem"]')
    expect(items.map((item) => item.text())).toEqual(['profile', 'logout'])
  })

  it('todo controle tem nome acessível e cada grade aponta para seu painel', async () => {
    wrapper = await mountApp(false)

    for (const slot of wrapper.findAll('[data-topbar-slot]')) {
      const name = slot.attributes('aria-label') || slot.attributes('title')
      if (slot.attributes('data-topbar-slot') !== 'profile') {
        expect(name, `slot ${slot.attributes('data-topbar-slot')} sem nome acessível`).toBeTruthy()
      }
    }

    expect(wrapper.find('[data-topbar-slot="grid-left"]').attributes('aria-controls')).toBe('app-sidebar')
    expect(wrapper.find('[data-topbar-slot="grid-right"]').attributes('aria-controls')).toBe('app-copilot')
    expect(wrapper.find('[data-topbar-slot="profile"]').find('.profile-trigger').attributes('aria-label')).toContain('admin')
  })
})
