import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConsoleOutput from '../src/components/ConsoleOutput.vue'
import { consoleStore } from '../src/store/console.js'

let wrapper = null

function appendMessages() {
  consoleStore.append({ actor: 'Vivy', kind: 'agent', text: 'Primeira linha.', at: '2026-10-19T09:00:00.000Z' })
  consoleStore.append({ actor: 'David', kind: 'agent', text: 'Segunda linha.', at: '2026-10-19T09:05:00.000Z' })
}

function sizeLog(scrollHeight, clientHeight) {
  const scroller = wrapper.find('.console-output').element
  Object.defineProperty(scroller, 'scrollHeight', { configurable: true, value: scrollHeight })
  Object.defineProperty(scroller, 'clientHeight', { configurable: true, value: clientHeight })
  return scroller
}

beforeEach(() => {
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
    expect(wrapper.text()).toContain('No messages')
  })

  it('keeps scrolling to the end while pinned when a new message arrives', async () => {
    wrapper = mount(ConsoleOutput, { attachTo: document.body })
    await nextTick()

    const scroller = sizeLog(1000, 200)
    scroller.scrollTop = 1000
    await wrapper.find('.console-output').trigger('scroll')
    await nextTick()

    const grown = sizeLog(1400, 200)
    appendMessages()
    await nextTick()
    await nextTick()

    expect(grown.scrollTop).toBe(1400)
  })
})
