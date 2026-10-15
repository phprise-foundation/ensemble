import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
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

vi.mock('../src/api/index.js', () => ({
  api: {
    fetchUser: async () => ({ login: 'admin', displayName: '' }),
    updateUser: async (data) => ({ login: 'admin', displayName: data.displayName }),
  },
  isSimulated: () => false,
  setFeature: () => {},
  setAll: () => {},
  hasSimulation: { value: false },
  simulatedFeatures: { value: [] },
  simulation: { features: {} },
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
