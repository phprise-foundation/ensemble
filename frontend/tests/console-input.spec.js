import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ConsoleInput from '../src/components/ConsoleInput.vue'
import { consoleStore } from '../src/store/console.js'
import { runCommand } from '../src/store/commands.js'

vi.mock('../src/api/index.js', () => ({
  isSimulated: () => false,
  setFeature: () => {},
  setAll: () => {},
  hasSimulation: { value: false },
  simulatedFeatures: { value: [] },
  simulation: { features: {} },
}))

vi.mock('../src/store/commands.js', () => ({
  runCommand: vi.fn(),
}))

function type(wrapper, text) {
  wrapper.find('.console-input-field').setValue(text)
}

function submit(wrapper) {
  wrapper.find('form.console-input').trigger('submit')
}

describe('ConsoleInput', () => {
  beforeEach(() => {
    consoleStore.clear()
    consoleStore.state.busy = false
    runCommand.mockClear()
  })

  it('envia o comando digitado ao store e limpa o campo', async () => {
    const wrapper = mount(ConsoleInput)
    type(wrapper, '/help')
    submit(wrapper)
    await wrapper.vm.$nextTick()
    expect(runCommand).toHaveBeenCalledWith('/help')
    expect(wrapper.find('.console-input-field').element.value).toBe('')
  })

  it('não envia quando o campo está vazio', () => {
    const wrapper = mount(ConsoleInput)
    type(wrapper, '   ')
    submit(wrapper)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('desabilita input e botão com busy ativo e não reenvia', () => {
    consoleStore.state.busy = true
    const wrapper = mount(ConsoleInput)
    expect(wrapper.find('.console-input-field').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.console-input-send').attributes('disabled')).toBeDefined()
    type(wrapper, '/status')
    submit(wrapper)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('comando desconhecido é decidido pelo catálogo fechado do store', async () => {
    runCommand.mockImplementation((raw) => {
      consoleStore.append('command', 'user', raw)
      consoleStore.append('error', 'system', 'Comando desconhecido: /foo.')
    })
    const wrapper = mount(ConsoleInput)
    type(wrapper, '/foo')
    submit(wrapper)
    expect(wrapper.find('.console-input').text()).not.toContain('desconhecido')
    const kinds = consoleStore.state.messages.map((message) => message.kind)
    expect(kinds).toEqual(['command', 'error'])
  })

  it('/clear é tratado pelo store, sem lógica local no componente', () => {
    const wrapper = mount(ConsoleInput)
    type(wrapper, '/clear')
    submit(wrapper)
    expect(runCommand).toHaveBeenCalledWith('/clear')
  })

  it('↑ recupera o comando anterior e ↓ volta ao vazio', async () => {
    const wrapper = mount(ConsoleInput)
    type(wrapper, '/help')
    submit(wrapper)
    type(wrapper, '/status')
    submit(wrapper)
    const field = wrapper.find('.console-input-field')
    field.trigger('keydown', { key: 'ArrowUp' })
    await nextTick()
    expect(field.element.value).toBe('/status')
    field.trigger('keydown', { key: 'ArrowUp' })
    await nextTick()
    expect(field.element.value).toBe('/help')
    field.trigger('keydown', { key: 'ArrowDown' })
    await nextTick()
    expect(field.element.value).toBe('/status')
    field.trigger('keydown', { key: 'ArrowDown' })
    await nextTick()
    expect(field.element.value).toBe('')
  })

  it('não envia em duplicidade quando runCommand marca busy', async () => {
    runCommand.mockImplementation(() => {
      consoleStore.state.busy = true
    })
    const wrapper = mount(ConsoleInput)
    type(wrapper, '/status')
    submit(wrapper)
    await nextTick()
    expect(wrapper.find('.console-input-field').attributes('disabled')).toBeDefined()
    submit(wrapper)
    expect(runCommand).toHaveBeenCalledTimes(1)
  })
})
