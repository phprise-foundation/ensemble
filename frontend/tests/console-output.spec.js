import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConsoleOutput from '../src/components/ConsoleOutput.vue'
import { consoleStore } from '../src/store/console.js'

let wrapper = null

function setSize(element, scrollHeight, clientHeight) {
  Object.defineProperty(element, 'scrollHeight', { configurable: true, value: scrollHeight })
  Object.defineProperty(element, 'clientHeight', { configurable: true, value: clientHeight })
}

function appendMessages() {
  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Primeira linha.', at: '2026-10-19T09:00:00.000Z' })
  consoleStore.append({ actor: 'David', kind: 'agent', text: 'Segunda linha.', at: '2026-10-19T09:05:00.000Z' })
}

function log() {
  return wrapper.find('.console-output')
}

async function scrollTo(offset) {
  log().element.scrollTop = offset
  await log().trigger('scroll')
  await nextTick()
}

async function settle() {
  await nextTick()
  await nextTick()
  await nextTick()
}

beforeEach(() => {
  consoleStore.stop()
  consoleStore.clear()
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  consoleStore.clear()
})

describe('ConsoleOutput', () => {
  it('drops every rendered line when the store is cleared', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    appendMessages()
    await nextTick()

    expect(wrapper.findAll('.console-line')).toHaveLength(2)

    consoleStore.clear()
    await nextTick()

    expect(wrapper.findAll('.console-line')).toHaveLength(0)
    expect(wrapper.text()).toContain('Nenhuma mensagem')
  })

  it('keeps scrolling to the end while pinned when a new message arrives', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await settle()
    setSize(log().element, 1000, 200)
    await scrollTo(1000)

    setSize(log().element, 1400, 200)
    appendMessages()
    await settle()

    expect(log().element.scrollTop).toBe(1400)
    expect(consoleStore.state.unread).toBe(0)
  })

  it('pins to the bottom on mount without a scroll event', async () => {
    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Antes de abrir.', at: '2026-10-19T09:00:00.000Z' })

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    setSize(log().element, 1600, 200)
    await settle()

    expect(log().element.scrollTop).toBe(1600)
    expect(wrapper.find('.console-jump').exists()).toBe(false)
  })

  it('returns to the bottom when mounted with a pending unread log', async () => {
    appendMessages()
    appendMessages()

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    setSize(log().element, 900, 200)
    await settle()

    expect(log().element.scrollTop).toBe(900)
    expect(wrapper.find('.console-jump').exists()).toBe(false)
  })

  it('stays pinned while the gap to the bottom is within the tolerance', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await settle()
    setSize(log().element, 1000, 200)
    await scrollTo(776)

    appendMessages()
    await settle()

    expect(wrapper.find('.console-jump').exists()).toBe(false)
    expect(consoleStore.state.unread).toBe(0)
  })

  it('unpins when the reader scrolls past the tolerance', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await settle()
    setSize(log().element, 1000, 200)
    await scrollTo(700)

    appendMessages()
    await settle()

    expect(log().element.scrollTop).toBe(700)
    expect(wrapper.find('.console-jump').text()).toBe('2 novas')
  })

  it('shows no pill while idle unpinned without unread', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await settle()
    setSize(log().element, 1000, 200)
    await scrollTo(0)

    expect(wrapper.find('.console-jump').exists()).toBe(false)
  })

  it('counts unread while unpinned and lands at the bottom on click', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await settle()
    setSize(log().element, 1000, 200)
    await scrollTo(100)

    appendMessages()
    await settle()

    expect(wrapper.find('.console-jump').text()).toBe('2 novas')
    expect(consoleStore.state.unread).toBe(2)

    setSize(log().element, 1800, 200)
    await wrapper.find('.console-jump').trigger('click')
    await nextTick()

    expect(log().element.scrollTop).toBe(1800)
    expect(consoleStore.state.unread).toBe(0)
    expect(wrapper.find('.console-jump').exists()).toBe(false)
  })

  it('renders the time with the local locale format', async () => {
    const at = '2026-10-19T09:00:00.000Z'
    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Linha.', at })

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    expect(wrapper.find('.console-time').text()).toBe(new Date(at).toLocaleTimeString())
  })

  it('renders one class per kind and the actor', async () => {
    consoleStore.append({ actor: 'system', kind: 'system', text: 'Sistema.', at: '2026-10-19T09:00:00.000Z' })
    consoleStore.append({ actor: 'admin', kind: 'command', text: '/clear', at: '2026-10-19T09:00:01.000Z' })
    consoleStore.append({ actor: 'agent', kind: 'error', text: 'Falhou.', at: '2026-10-19T09:00:02.000Z' })

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    const kinds = wrapper.findAll('.console-line').map((line) => line.classes().filter((name) => name !== 'console-line'))
    expect(kinds).toEqual([['system'], ['command'], ['error']])
    expect(wrapper.findAll('.console-actor').map((node) => node.text())).toEqual(['system', 'admin', 'agent'])
  })

  it('never renders markup coming from a message', async () => {
    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: '<b>negrito</b>', at: '2026-10-19T09:00:00.000Z' })

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    expect(wrapper.find('.console-text').text()).toBe('<b>negrito</b>')
    expect(wrapper.find('.console-text b').exists()).toBe(false)
  })

  it('offers every distinct actor plus All in the filter', async () => {
    appendMessages()
    consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Outra.', at: '2026-10-19T09:10:00.000Z' })

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    const labels = wrapper.findAll('.console-filter option').map((option) => option.text())
    expect(labels).toEqual(['Todas', 'Vivy', 'David'])
  })

  it('filters only the rendering and keeps every message in the store', async () => {
    appendMessages()

    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    await wrapper.find('.console-filter').setValue('David')
    await nextTick()

    expect(wrapper.findAll('.console-actor').map((node) => node.text())).toEqual(['David'])
    expect(consoleStore.state.messages).toHaveLength(2)
  })
})
