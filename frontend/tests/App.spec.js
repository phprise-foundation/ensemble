import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
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
    fetchUser: vi.fn().mockResolvedValue({ login: 'admin', displayName: '' }),
    updateUser: vi.fn().mockResolvedValue({ login: 'admin', displayName: '' }),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ meta: { title: 'Test' } }),
  useRouter: () => ({ afterEach: () => {} }),
}))

vi.mock('../src/theme.js', () => ({
  theme: { mode: 'dark' },
  toggleTheme: () => {},
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

function isInert(element) {
  return element.hasAttribute('inert')
}

async function mountApp(mobile = true) {
  setupMatchMedia(mobile)
  const wrapper = mount(App, {
    global: {
      stubs: {
        RouterView: true,
        RouterLink: 'a',
        AppIcon: 'span',
      },
    },
    attachTo: document.body,
  })
  await nextTick()
  return wrapper
}

describe('App drawer overlay', () => {
  beforeEach(() => {
    document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  })

  it('marks the background content inert while the overlay drawer is open', async () => {
    const wrapper = await mountApp(true)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()

    expect(wrapper.find('.scrim').exists()).toBe(true)
    expect(isInert(wrapper.find('.content').element)).toBe(true)

    await wrapper.find('.scrim').trigger('click')
    await nextTick()

    expect(wrapper.find('.scrim').exists()).toBe(false)
    expect(isInert(wrapper.find('.content').element)).toBe(false)
  })

  it('keeps the background content focusable on desktop', async () => {
    const wrapper = await mountApp(false)

    await wrapper.find('.menu-toggle').trigger('click')
    await nextTick()

    expect(wrapper.find('.scrim').exists()).toBe(false)
    expect(isInert(wrapper.find('.content').element)).toBe(false)
  })
})

const SidebarStub = {
  name: 'AppSidebar',
  props: { open: Boolean, isMobile: Boolean, collapsed: Boolean },
  template: '<div class="sidebar-stub"></div>',
}

const CopilotStub = {
  name: 'AppCopilot',
  template: '<section id="app-copilot" class="copilot-panel-stub"></section>',
}

function mountAppWithSidebarStub() {
  setupMatchMedia(false)
  return mount(App, {
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
}

function pressShortcut(modifier) {
  const event = new window.KeyboardEvent('keydown', { key: 'b', cancelable: true, bubbles: true, ...modifier })
  window.dispatchEvent(event)
  return event
}

describe('App sidebar collapse toggle', () => {
  beforeEach(() => {
    document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  })

  it('toggles the collapsed prop of the sidebar from the top button', async () => {
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    const toggle = wrapper.find('.sidebar-toggle')
    expect(toggle.exists()).toBe(true)
    expect(toggle.attributes('aria-label')).toBe('Toggle sidebar')
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(false)

    await toggle.trigger('click')
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(true)
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(false)
    expect(toggle.attributes('aria-expanded')).toBe('true')
    wrapper.unmount()
  })

  it('toggles with Ctrl+B and Cmd+B without the browser default', async () => {
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    const control = pressShortcut({ ctrlKey: true })
    await nextTick()
    expect(control.defaultPrevented).toBe(true)
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(true)

    pressShortcut({ metaKey: true })
    await nextTick()
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(false)
    wrapper.unmount()
  })

  it('removes the shortcut listener on unmount', async () => {
    const removeListener = vi.spyOn(window, 'removeEventListener')
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    wrapper.unmount()

    expect(removeListener).toHaveBeenCalledWith('keydown', expect.any(Function))
    removeListener.mockRestore()
  })
})

describe('App copilot panel toggle', () => {
  beforeEach(() => {
    document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  })

  it('renders the panel open on desktop and toggles it from the top button', async () => {
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    const toggle = wrapper.find('.copilot-toggle')
    expect(toggle.exists()).toBe(true)
    expect(toggle.attributes('aria-label')).toBe('Toggle copilot')
    expect(toggle.attributes('aria-controls')).toBe('app-copilot')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('#app-copilot').exists()).toBe(true)

    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('#app-copilot').exists()).toBe(false)

    await toggle.trigger('click')
    expect(wrapper.find('#app-copilot').exists()).toBe(true)
    wrapper.unmount()
  })

  it('toggles the copilot with Ctrl+Alt+B without touching the sidebar', async () => {
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    const control = pressShortcut({ ctrlKey: true, altKey: true })
    await nextTick()
    expect(control.defaultPrevented).toBe(true)
    expect(wrapper.find('#app-copilot').exists()).toBe(false)
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(false)

    pressShortcut({ metaKey: true, altKey: true })
    await nextTick()
    expect(wrapper.find('#app-copilot').exists()).toBe(true)
    wrapper.unmount()
  })

  it('toggles the sidebar with Ctrl+B without touching the copilot', async () => {
    const wrapper = mountAppWithSidebarStub()
    await nextTick()

    pressShortcut({ ctrlKey: true })
    await nextTick()
    expect(wrapper.findComponent(SidebarStub).props('collapsed')).toBe(true)
    expect(wrapper.find('#app-copilot').exists()).toBe(true)
    wrapper.unmount()
  })

  it('hides the copilot button and panel on mobile', async () => {
    const wrapper = await mountApp(true)

    expect(wrapper.find('.copilot-toggle').exists()).toBe(false)
    expect(wrapper.find('.copilot-panel-stub').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('App session gate', () => {
  beforeEach(() => {
    session.signIn()
  })

  afterEach(() => {
    session.signIn()
  })

  it('hides every protected region and offers a neutral view after logout', async () => {
    const wrapper = await mountApp(false)

    expect(wrapper.find('.topbar').exists()).toBe(true)
    expect(wrapper.find('.profile-menu').exists()).toBe(true)

    session.signOut()
    await nextTick()

    expect(wrapper.find('.app').exists()).toBe(false)
    expect(wrapper.find('.topbar').exists()).toBe(false)
    expect(wrapper.find('.content').exists()).toBe(false)
    expect(wrapper.find('.session-ended').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sign in to your workspace')
    wrapper.unmount()
  })

  it('restores the shell when sign in again is activated', async () => {
    session.signOut()
    const wrapper = await mountApp(false)

    await wrapper.find('.session-ended .button').trigger('click')
    await nextTick()

    expect(session.signedIn).toBe(true)
    expect(wrapper.find('.app').exists()).toBe(true)
    expect(wrapper.find('.topbar').exists()).toBe(true)
    wrapper.unmount()
  })
})
