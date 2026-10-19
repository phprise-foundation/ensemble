import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import ConsoleInput from '../src/components/ConsoleInput.vue'
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

const { consoleStore } = await import('../src/store/console.js')

function input(wrapper) {
  return wrapper.find('.console-input-field')
}

function send(wrapper) {
  return wrapper.find('form.console-input').trigger('submit')
}

async function type(wrapper, text) {
  await input(wrapper).setValue(text)
}

describe('ConsoleInput', () => {
  beforeEach(() => {
    consoleStore.clear()
    consoleStore.state.busy = false
    runCommand.mockReset()
  })

  it('encaminha o comando digitado ao catálogo e limpa o campo', async () => {
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '/status')
    await send(wrapper)
    expect(runCommand).toHaveBeenCalledWith('/status')
    expect(input(wrapper).element.value).toBe('')
  })

  it('normaliza espaços antes de encaminhar', async () => {
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '  /list  ')
    await send(wrapper)
    expect(runCommand).toHaveBeenCalledWith('/list')
  })

  it('não encaminha campo vazio ou em branco', async () => {
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '   ')
    await send(wrapper)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('com busy ativo desabilita input e botão e ignora o submit', async () => {
    runCommand.mockImplementation(async () => {
      consoleStore.state.busy = true
      await nextTick()
    })
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '/status')
    await send(wrapper)
    await nextTick()
    expect(input(wrapper).attributes('disabled')).toBeDefined()
    expect(wrapper.find('.console-input-send').attributes('disabled')).toBeDefined()
    await send(wrapper)
    expect(runCommand).toHaveBeenCalledTimes(1)
  })

  it('não bloqueia o próximo comando depois que o anterior termina', async () => {
    runCommand.mockImplementation(async () => {
      consoleStore.state.busy = true
      await nextTick()
      consoleStore.state.busy = false
    })
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '/help')
    await send(wrapper)
    await nextTick()
    expect(input(wrapper).attributes('disabled')).toBeUndefined()
    await type(wrapper, '/list')
    await send(wrapper)
    expect(runCommand).toHaveBeenCalledTimes(2)
  })

  it('histórico ↑/↓ recupera do mais recente e volta ao vazio', async () => {
    const wrapper = mount(ConsoleInput)
    await type(wrapper, '/help')
    await send(wrapper)
    await type(wrapper, '/status')
    await send(wrapper)
    await type(wrapper, 'rascunho')
    await input(wrapper).trigger('keydown', { key: 'ArrowUp' })
    expect(input(wrapper).element.value).toBe('/status')
    await input(wrapper).trigger('keydown', { key: 'ArrowUp' })
    expect(input(wrapper).element.value).toBe('/help')
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(input(wrapper).element.value).toBe('/status')
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(input(wrapper).element.value).toBe('rascunho')
  })

  it('histórico vazio deixa as setas inertes', async () => {
    const wrapper = mount(ConsoleInput)
    await type(wrapper, 'rascunho')
    await input(wrapper).trigger('keydown', { key: 'ArrowUp' })
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(input(wrapper).element.value).toBe('rascunho')
  })
})

describe('ConsoleInput com o catálogo real', () => {
  let RealConsoleInput

  async function mountHost() {
    const { runCommand: realRunCommand } = await vi.importActual('../src/store/commands.js')
    runCommand.mockReset()
    runCommand.mockImplementation(realRunCommand)
    RealConsoleInput ??= (await vi.importActual('../src/components/ConsoleInput.vue')).default
    const Host = defineComponent({
      setup() {
        return () => h(RealConsoleInput)
      },
    })
    return mount(Host)
  }

  async function settle() {
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 0))
    await nextTick()
  }

  async function post(wrapper, text) {
    await type(wrapper, text)
    await send(wrapper)
    await settle()
  }

  beforeEach(async () => {
    const { consoleStore: store } = await import('../src/store/console.js')
    const { boardStore } = await import('../src/store/board.js')
    store.clear()
    store.state.busy = false
    boardStore.state.boards = [{ id: 'board-1', summary: 'Board de testes' }]
    boardStore.state.boardId = 'board-1'
    boardStore.state.statuses = [
      { id: 'todo', name: 'A fazer', type: 'active' },
      { id: 'done', name: 'Feito', type: 'final' },
    ]
    boardStore.state.teams = [{ id: 'team-1', name: 'Time Kaio' }]
    boardStore.state.agents = [{ id: 'agent-1', name: 'Vivy', teamId: 'team-1' }]
    boardStore.state.issues = [
      { id: 'KAN-1', summary: 'Item ativo', statusId: 'todo', assigneeId: 'agent-1', boardId: 'board-1' },
      { id: 'KAN-2', summary: 'Item final', statusId: 'done', ownerId: 'agent-1', boardId: 'board-1' },
    ]
  })

  it('/help mostra o catálogo fechado no output', async () => {
    const wrapper = await mountHost()
    await post(wrapper, '/help')
    expect(consoleStore.state.messages.map((message) => message.kind)).toEqual(['command', 'system'])
    const help = consoleStore.state.messages[1].text
    expect(help.startsWith('Comandos disponíveis:')).toBe(true)
    for (const command of ['/status', '/list', '/clear', '/help']) {
      expect(help).toContain(`\n${command} —`)
    }
  })

  it('/status e /list leem o board carregado', async () => {
    const wrapper = await mountHost()
    await post(wrapper, '/status')
    await post(wrapper, '/list')
    const texts = consoleStore.state.messages.map((message) => message.text).join('\n')
    expect(texts).toContain('Agentes:')
    expect(texts).toContain('Time Kaio: Vivy')
    expect(texts).toContain('KAN-1 · Item ativo · A fazer · agent-1')
    expect(texts).not.toContain('KAN-2')
  })

  it('comando desconhecido vira erro no output e não é avaliado no componente', async () => {
    const wrapper = await mountHost()
    await post(wrapper, '/invoke rm -rf')
    expect(consoleStore.state.messages.map((message) => message.kind)).toEqual(['command', 'error'])
    expect(consoleStore.state.messages[1].text).toContain('Comando desconhecido: /invoke')
    expect(wrapper.text()).not.toContain('desconhecido')
  })

  it('/clear limpa apenas a UI local, sem tocar no board', async () => {
    const wrapper = await mountHost()
    await post(wrapper, '/status')
    expect(consoleStore.state.messages.length).toBeGreaterThan(0)
    await post(wrapper, '/clear')
    expect(consoleStore.state.messages).toEqual([])
    const { boardStore } = await import('../src/store/board.js')
    expect(boardStore.state.issues).toHaveLength(2)
  })
})
