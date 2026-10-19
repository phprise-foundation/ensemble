import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConsoleOutput from '../src/components/ConsoleOutput.vue'
import { consoleStore } from '../src/store/console.js'

vi.mock('../src/api/index.js', () => ({
  isSimulated: () => false,
  setFeature: () => {},
  setAll: () => {},
  hasSimulation: { value: false },
  simulatedFeatures: { value: [] },
  simulation: { features: {} },
}))

const at = '2026-10-19T10:20:30.000Z'

function mountConsole() {
  return mount(ConsoleOutput, { attachTo: document.body })
}

function stubScroll(element, metrics) {
  Object.defineProperty(element, 'scrollHeight', { configurable: true, value: metrics.scrollHeight })
  Object.defineProperty(element, 'clientHeight', { configurable: true, value: metrics.clientHeight })
  Object.defineProperty(element, 'scrollTop', {
    configurable: true,
    value: metrics.scrollTop,
    writable: true,
  })
}

describe('ConsoleOutput', () => {
  beforeEach(() => {
    consoleStore.clear()
  })

  it('renderiza cada mensagem com classe por kind, horário e ator', () => {
    consoleStore.append('command', 'user', '/help')
    consoleStore.append('system', 'system', 'comandos disponíveis')
    const wrapper = mountConsole()
    const lines = wrapper.findAll('.console-line')
    expect(lines).toHaveLength(2)
    expect(lines[0].classes()).toContain('command')
    expect(lines[1].classes()).toContain('system')
    expect(lines[0].text()).toContain('user')
    expect(lines[0].text()).toContain(consoleStore.state.messages[0].at.slice(11, 19))
    expect(wrapper.find('.console-output').attributes('role')).toBe('log')
  })

  it('mostra placeholder discreto quando não há mensagens', () => {
    const wrapper = mountConsole()
    expect(wrapper.find('.console-empty').exists()).toBe(true)
    expect(wrapper.find('.console-line').exists()).toBe(false)
  })

  it('interpola o texto sem usar v-html', () => {
    consoleStore.append('agent', 'Ian', '<img src=x onerror=alert(1)>')
    const wrapper = mountConsole()
    expect(wrapper.html()).not.toContain('<img')
    expect(wrapper.find('.console-text').text()).toBe('<img src=x onerror=alert(1)>')
  })

  it('o filtro por ator muda só a renderização, sem apagar mensagens', async () => {
    consoleStore.append('agent', 'Ian', 'primeiro')
    consoleStore.append('agent', 'Lucy', 'segundo')
    const wrapper = mountConsole()
    await wrapper.find('select.console-filter').setValue('Lucy')
    const texts = wrapper.findAll('.console-line').map((line) => line.text())
    expect(texts).toHaveLength(1)
    expect(texts[0]).toContain('Lucy')
    expect(consoleStore.state.messages).toHaveLength(2)
  })

  it('autoscroll acompanha o fundo quando ancorado', async () => {
    const wrapper = mountConsole()
    const element = wrapper.find('.console-output').element
    stubScroll(element, { scrollHeight: 500, clientHeight: 200, scrollTop: 300 })
    consoleStore.append('agent', 'Ian', 'nova mensagem')
    await nextTick()
    expect(element.scrollTop).toBe(500)
  })

  it('com o scroll travado a pílula conta as novas e o clique volta ao fundo zerando unread', async () => {
    const wrapper = mountConsole()
    const element = wrapper.find('.console-output').element
    stubScroll(element, { scrollHeight: 500, clientHeight: 200, scrollTop: 0 })
    element.dispatchEvent(new Event('scroll'))
    expect(wrapper.find('.console-jump').exists()).toBe(false)
    consoleStore.append('agent', 'Ian', 'uma')
    consoleStore.append('agent', 'Lucy', 'duas')
    await nextTick()
    const pill = wrapper.find('.console-jump')
    expect(pill.exists()).toBe(true)
    expect(pill.text()).toBe('2 novas')
    stubScroll(element, { scrollHeight: 500, clientHeight: 200, scrollTop: 0 })
    await pill.trigger('click')
    await nextTick()
    expect(consoleStore.state.unread).toBe(0)
    expect(element.scrollTop).toBe(500)
    expect(wrapper.find('.console-jump').exists()).toBe(false)
  })
})
