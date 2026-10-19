import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AppSidebar from '../src/components/AppSidebar.vue'

vi.mock('../src/store/board.js', () => ({
  boardStore: {
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
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({}),
  useRouter: () => ({ afterEach: () => {} }),
}))

function focusableInside(container) {
  const selector =
    'button:not([disabled]), [href]:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  return Array.from(container.querySelectorAll(selector)).filter((element) => !element.hidden && element.isConnected)
}

function dispatchKeyboard(key, shiftKey = false) {
  document.dispatchEvent(new window.KeyboardEvent('keydown', { key, shiftKey, bubbles: true }))
}

function mountSidebar(props) {
  return mount(AppSidebar, {
    props,
    global: { stubs: { RouterLink: 'a' } },
    attachTo: document.body,
  })
}

describe('AppSidebar drawer accessibility', () => {
  it('moves focus to the first focusable element when opened in overlay mode', async () => {
    const wrapper = mountSidebar({ open: false, isMobile: false })
    await wrapper.setProps({ open: true, isMobile: true })
    await nextTick()
    expect(document.activeElement?.closest('#app-sidebar')).toBeTruthy()
  })

  it('emits close on Escape and detaches the listener when the parent closes the drawer', async () => {
    const wrapper = mountSidebar({ open: true, isMobile: true })
    await nextTick()
    dispatchKeyboard('Escape')
    await nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)

    await wrapper.setProps({ open: false })
    await nextTick()
    dispatchKeyboard('Escape')
    await nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('cycles Tab forward from the last element to the first inside the drawer', async () => {
    const wrapper = mountSidebar({ open: true, isMobile: true })
    await nextTick()
    const focusable = focusableInside(wrapper.find('#app-sidebar').element)
    expect(focusable.length).toBeGreaterThan(1)
    focusable[focusable.length - 1].focus()
    await nextTick()
    dispatchKeyboard('Tab')
    await nextTick()
    expect(document.activeElement).toBe(focusable[0])
  })

  it('cycles Shift+Tab backward from the first element to the last inside the drawer', async () => {
    const wrapper = mountSidebar({ open: true, isMobile: true })
    await nextTick()
    const focusable = focusableInside(wrapper.find('#app-sidebar').element)
    expect(focusable.length).toBeGreaterThan(1)
    focusable[0].focus()
    await nextTick()
    dispatchKeyboard('Tab', true)
    await nextTick()
    expect(document.activeElement).toBe(focusable[focusable.length - 1])
  })

  it('announces the drawer as a modal dialog only when open in overlay mode', async () => {
    const wrapper = mountSidebar({ open: true, isMobile: true })
    await nextTick()
    const aside = wrapper.find('#app-sidebar')
    expect(aside.attributes('role')).toBe('dialog')
    expect(aside.attributes('aria-modal')).toBe('true')
    expect(aside.attributes('aria-hidden')).toBe('false')
  })

  it('keeps plain navigation semantics when used as a desktop sidebar', async () => {
    const wrapper = mountSidebar({ open: false, isMobile: false })
    await nextTick()
    const aside = wrapper.find('#app-sidebar')
    expect(aside.attributes('role')).toBeUndefined()
    expect(aside.attributes('aria-modal')).toBeUndefined()
    expect(aside.attributes('aria-hidden')).toBeUndefined()
  })

  it('keeps the tree in the drawer when the collapsed flag is inherited from desktop', async () => {
    const wrapper = mountSidebar({ open: true, isMobile: true, collapsed: true })
    await nextTick()
    expect(wrapper.find('nav.nav.rail').exists()).toBe(false)
    expect(wrapper.find('.nav:not(.rail)').exists()).toBe(true)
    expect(wrapper.find('#app-sidebar .brand').exists()).toBe(true)
  })
})
