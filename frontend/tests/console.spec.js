import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import App from '../src/App.vue'
import ConsoleInput from '../src/components/ConsoleInput.vue'
import ConsoleOutput from '../src/components/ConsoleOutput.vue'
import { consoleStore } from '../src/store/console.js'
import { DEFAULT_CONSOLE_HEIGHT } from '../src/store/consoleHeight.js'

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

const { fetchIssues } = vi.hoisted(() => ({ fetchIssues: vi.fn() }))

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
    fetchIssues,
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

const DEFAULT_HEIGHT = DEFAULT_CONSOLE_HEIGHT
const MIN_HEIGHT = 120
const STEP = 16

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

function mountApp(isMobile = false) {
  setupMatchMedia(isMobile)
  document.documentElement.style.setProperty('--breakpoint-sidebar', '100px')
  return mount(App, {
    global: {
      stubs: {
        RouterView: true,
        RouterLink: 'a',
        AppIcon: 'span',
        AppSidebar: true,
        AppCopilot: true,
      },
    },
    attachTo: document.body,
  })
}

function pressCtrlJ() {
  const event = new window.KeyboardEvent('keydown', {
    key: 'j',
    ctrlKey: true,
    cancelable: true,
    bubbles: true,
  })
  window.dispatchEvent(event)
  return event
}

function heightOf(wrapper) {
  return wrapper.element.style.getPropertyValue('--console-height')
}

function mountConsoleInApp() {
  setupMatchMedia(false)
  return mount(App, {
    global: {
      stubs: {
        ConsoleInput: true,
        ConsoleOutput: true,
      },
    },
    attachTo: document.body,
  })
}

function appendMessages() {
  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Linha da Vivy.', at: '2026-10-19T09:00:00.000Z' })
  consoleStore.append({ actor: 'David', kind: 'agent', text: 'Linha do David.', at: '2026-10-19T09:05:00.000Z' })
  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Segunda linha da Vivy.', at: '2026-10-19T09:10:00.000Z' })
}

async function settle() {
  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 0))
  await nextTick()
}

beforeEach(() => {
  consoleStore.stop()
  consoleStore.clear()
  fetchIssues.mockReset()
  fetchIssues.mockResolvedValue([])
})

function dispatchPointer(type, target, clientY) {
  target.dispatchEvent(new window.PointerEvent(type, { clientY, bubbles: true, cancelable: true }))
}

describe('App console grid', () => {
  it('is born closed and toggles from the console button', async () => {
    const wrapper = mountApp(false)
    await nextTick()

    expect(wrapper.find('#app-console').exists()).toBe(false)
    const toggle = wrapper.find('.console-toggle')
    expect(toggle.exists()).toBe(true)
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    await nextTick()

    const panel = wrapper.find('#app-console')
    expect(panel.exists()).toBe(true)
    expect(panel.find('[role="log"]').exists()).toBe(true)
    expect(panel.find('form.console-input').exists()).toBe(true)
    expect(wrapper.find('.console-toggle').attributes('aria-expanded')).toBe('true')

    await wrapper.find('.console-toggle').trigger('click')
    await consoleStore.pollOnce()
    fetchIssues.mockClear()
    await nextTick()

    expect(wrapper.find('#app-console').exists()).toBe(false)
    expect(wrapper.find('.console-toggle').attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('toggles with Ctrl+J on desktop and prevents the browser default', async () => {
    const wrapper = mountApp(false)
    await nextTick()

    const event = pressCtrlJ()
    await nextTick()

    expect(event.defaultPrevented).toBe(true)
    expect(wrapper.find('#app-console').exists()).toBe(true)
    expect(wrapper.find('.console-toggle').attributes('aria-expanded')).toBe('true')

    pressCtrlJ()
    await nextTick()

    expect(wrapper.find('#app-console').exists()).toBe(false)
    expect(wrapper.find('.console-toggle').attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('is inert on mobile: no toggle button and the shortcut does nothing', async () => {
    const wrapper = mountApp(true)
    await nextTick()

    expect(wrapper.find('.console-toggle').exists()).toBe(false)

    pressCtrlJ()
    await nextTick()

    expect(wrapper.find('#app-console').exists()).toBe(false)
    wrapper.unmount()
  })

  it('labels the log in pt-BR and pluralizes the unread pill', async () => {
    const wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    expect(wrapper.find('form.console-input').exists()).toBe(false)
    expect(wrapper.find('.console-empty').text()).toBe('Nenhuma mensagem')
    expect(wrapper.find('.console-filter').attributes('aria-label')).toBe('Filtrar por ator')
    expect(wrapper.find('.console-filter option').text()).toBe('Todas')
    expect(wrapper.find('.console-activity').attributes('aria-label')).toBe('Ocioso')

    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'uma' })
    await nextTick()
    await nextTick()
    expect(wrapper.findAll('.console-filter option').map((node) => node.text())).toContain('Todas')

    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'duas' })
    await nextTick()
    expect(wrapper.find('.console-activity').attributes('aria-label')).toBe('Nova saída')
    wrapper.unmount()
  })
})

describe('AppConsole resizing', () => {
  async function openConsole() {
    const app = mountConsoleInApp()
    await nextTick()
    await nextTick()
    await app.find('.console-toggle').trigger('click')
    await nextTick()
    const panel = app.find('#app-console')
    return { app, panel, handle: panel.find('.console-resize') }
  }

  it('starts at 240px and grows or shrinks by 16px with the arrow keys', async () => {
    const { app, panel, handle } = await openConsole()

    expect(heightOf(panel)).toBe(`${DEFAULT_HEIGHT}px`)

    await handle.trigger('keydown', { key: 'ArrowUp' })
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT + STEP}px`)

    await handle.trigger('keydown', { key: 'ArrowDown' })
    await handle.trigger('keydown', { key: 'ArrowDown' })
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT - STEP}px`)
    app.unmount()
  })

  it('clamps the height at the 120px minimum', async () => {
    const { app, handle } = await openConsole()

    for (let index = 0; index < 20; index += 1) {
      await handle.trigger('keydown', { key: 'ArrowDown' })
    }
    await nextTick()

    expect(heightOf(app.find('#app-console'))).toBe(`${MIN_HEIGHT}px`)
    app.unmount()
  })

  it('restores 240px on double click over the handle', async () => {
    const { app, handle } = await openConsole()

    await handle.trigger('keydown', { key: 'ArrowUp' })
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT + STEP}px`)

    await handle.trigger('dblclick')
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT}px`)
    app.unmount()
  })

  it('resizes with the pointer drag and stops following once released', async () => {
    const { app, handle } = await openConsole()

    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT}px`)

    dispatchPointer('pointerdown', handle.element, 600)
    dispatchPointer('pointermove', window, 500)
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT + 100}px`)

    dispatchPointer('pointerup', window, 500)
    dispatchPointer('pointermove', window, 300)
    await nextTick()
    expect(heightOf(app.find('#app-console'))).toBe(`${DEFAULT_HEIGHT + 100}px`)
    app.unmount()
  })

  it('clamps the pointer drag at the 60vh ceiling', async () => {
    const { app, handle } = await openConsole()
    const ceiling = Math.round(window.innerHeight * 0.6)

    dispatchPointer('pointerdown', handle.element, 700)
    dispatchPointer('pointermove', window, -5000)
    await nextTick()

    expect(heightOf(app.find('#app-console'))).toBe(`${Math.max(ceiling, MIN_HEIGHT)}px`)
    dispatchPointer('pointerup', window, -5000)
    app.unmount()
  })

  it('closes from the header button of the grid', async () => {
    const app = mountConsoleInApp()
    await nextTick()
    await nextTick()
    await app.find('.console-toggle').trigger('click')
    await nextTick()

    await app.find('button[aria-label="Fechar console"]').trigger('click')
    await nextTick()

    expect(app.find('#app-console').exists()).toBe(false)
    expect(app.find('.console-toggle').attributes('aria-expanded')).toBe('false')
    app.unmount()
  })

  it('keeps the height in App.vue so the grid survives closing and reopening', async () => {
    const app = mountApp(false)
    await nextTick()

    expect(app.find('.console-toggle').attributes('style')).toBeUndefined()

    await app.find('.console-toggle').trigger('click')
    await nextTick()

    const panel = app.find('#app-console')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('style')).toContain(`--console-height: ${DEFAULT_HEIGHT}px`)

    await panel.find('.console-resize').trigger('keydown', { key: 'ArrowUp' })
    await nextTick()

    expect(app.find('#app-console').attributes('style')).toContain(`--console-height: ${DEFAULT_HEIGHT + STEP}px`)

    await app.find('.console-toggle').trigger('click')
    await nextTick()
    await app.find('.console-toggle').trigger('click')
    await nextTick()

    expect(app.find('#app-console').attributes('style')).toContain(`--console-height: ${DEFAULT_HEIGHT + STEP}px`)
    app.unmount()
  })
})

describe('console polling', () => {
  it('keeps the rejected promise inside the store and records a single error line', async () => {
    fetchIssues.mockRejectedValue(new Error('GET /api/issues falhou'))

    await expect(consoleStore.pollOnce()).resolves.toBeUndefined()
    await expect(consoleStore.pollOnce()).resolves.toBeUndefined()

    expect(consoleStore.state.messages).toHaveLength(1)
    expect(consoleStore.state.messages[0]).toMatchObject({ actor: 'system', kind: 'error' })
    expect(fetchIssues).toHaveBeenCalledTimes(2)
  })

  it('does not overlap ticks while one poll is still in flight', async () => {
    let release = () => {}
    fetchIssues.mockReturnValue(new Promise((resolve) => {
      release = resolve
    }))

    const first = consoleStore.pollOnce()
    const second = consoleStore.pollOnce()
    expect(fetchIssues).toHaveBeenCalledTimes(1)

    release([])
    await Promise.all([first, second])
    expect(fetchIssues).toHaveBeenCalledTimes(1)

    fetchIssues.mockResolvedValue([])
    await consoleStore.pollOnce()
    expect(fetchIssues).toHaveBeenCalledTimes(2)
  })
})

describe('console reopen', () => {
  it('an immediate poll after reopening fills the log with the agent comments', async () => {
    fetchIssues.mockResolvedValue([
      { id: 'KANB-1', summary: 'item', statusId: 'coding', comments: [{ id: 'c1', author: 'Vivy', body: 'revisão pronta', at: '2026-10-19T10:00:00.000Z' }] },
    ])
    const wrapper = mountApp()

    await wrapper.find('.console-toggle').trigger('click')
    await consoleStore.pollOnce()
    fetchIssues.mockClear()

    pressCtrlJ()
    await settle()
    expect(wrapper.find('#app-console').exists()).toBe(false)
    expect(consoleStore.state.polling).toBe(false)

    pressCtrlJ()
    await settle()

    expect(wrapper.find('#app-console').exists()).toBe(true)
    expect(fetchIssues.mock.calls.length).toBeGreaterThanOrEqual(1)
    expect(wrapper.findAll('.console-line').map((node) => node.find('.console-text').text())).toEqual(['revisão pronta'])
    consoleStore.stop()
    wrapper.unmount()
  })
})

describe('ConsoleOutput', () => {
  it('filters lines by the selected actor while the store keeps every message', async () => {
    appendMessages()
    const wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    expect(wrapper.findAll('.console-line')).toHaveLength(3)

    await wrapper.find('.console-filter').setValue('Vivy')
    await nextTick()

    const actors = wrapper.findAll('.console-actor').map((node) => node.text())
    expect(actors).toEqual(['Vivy', 'Vivy'])
    expect(wrapper.findAll('.console-text').map((node) => node.text())).toEqual([
      'Linha da Vivy.',
      'Segunda linha da Vivy.',
    ])
    expect(consoleStore.state.messages).toHaveLength(3)
    wrapper.unmount()
  })

  it('exposes the log as an aria live region', async () => {
    const wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    const log = wrapper.find('.console-output')
    expect(log.attributes('role')).toBe('log')
    expect(log.attributes('aria-live')).toBe('polite')
    wrapper.unmount()
  })

  it('shows the jump pill when unpinned with unread and clears unread on click', async () => {
    const wrapper = mount(ConsoleOutput, { attachTo: document.body })
    const scroller = wrapper.find('.console-output').element
    Object.defineProperty(scroller, 'scrollHeight', { configurable: true, value: 1000 })
    Object.defineProperty(scroller, 'clientHeight', { configurable: true, value: 200 })
    await nextTick()
    await nextTick()
    scroller.scrollTop = 0

    await wrapper.find('.console-output').trigger('scroll')
    await nextTick()

    expect(wrapper.find('.console-jump').exists()).toBe(false)

    appendMessages()
    await settle()

    const jump = wrapper.find('.console-jump')
    expect(jump.exists()).toBe(true)
    expect(jump.text()).toBe('3 novas')
    expect(consoleStore.state.unread).toBe(3)

    await jump.trigger('click')

    expect(consoleStore.state.unread).toBe(0)
    await nextTick()
    expect(wrapper.find('.console-jump').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('ConsoleInput', () => {
  it('labels the command bar in pt-BR', async () => {
    const wrapper = mount(ConsoleInput)
    await nextTick()

    expect(wrapper.find('form.console-input').attributes('aria-label')).toBe('Entrada do console')
    expect(wrapper.find('.console-input-field').attributes('placeholder')).toBe('Digite um comando… (/help)')
    expect(wrapper.find('.console-input-send').text()).toBe('Enviar')
    wrapper.unmount()
  })
})
