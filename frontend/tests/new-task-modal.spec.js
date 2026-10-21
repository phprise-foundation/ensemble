import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'

const { api } = vi.hoisted(() => ({ api: { createIssue: vi.fn(), fetchIssues: vi.fn() } }))

vi.mock('../src/api/index.js', () => ({ api }))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { boardId: 'KANB' } }),
}))

import { createSeed } from '../src/data/seed.js'
import { boardStore } from '../src/store/board.js'
import BoardPage from '../src/views/BoardPage.vue'

const initialStatusesOf = (seed) =>
  seed.statuses.filter((status) => status.workflowId === 'standard' && status.type === 'initial')

const mountBoardPage = () => mount(BoardPage, {
  attachTo: document.body,
  global: {
    stubs: { BoardColumn: true, IssuePanel: true, AppIcon: 'span', FieldLimit: true },
  },
})

const createButton = (wrapper) => wrapper.findAll('footer .button')[1]

async function openModal() {
  const wrapper = mountBoardPage()
  await wrapper.find('.board-toolbar .button').trigger('click')
  await flushPromises()
  await nextTick()
  return wrapper
}

describe('BoardPage: modal de nova task e status inicial', () => {
  let seed

  beforeEach(() => {
    seed = createSeed()
    Object.assign(boardStore.state, seed, { boardId: 'KANB', selectedIssueId: null })
    api.createIssue.mockReset()
    api.fetchIssues.mockReset()
    api.fetchIssues.mockResolvedValue(seed.issues)
  })

  afterEach(() => {
    document.querySelectorAll('body > #app').forEach((element) => element.remove())
  })

  it('abre o modal com foco no summary e opções limitadas aos statuses initial', async () => {
    const wrapper = await openModal()

    expect(wrapper.find('.new-task-modal').exists()).toBe(true)
    expect(document.activeElement.id).toBe('create-summary')
    const options = [...wrapper.find('#create-status').element.options].map((option) => option.value)
    expect(options).toEqual(initialStatusesOf(seed).map((status) => status.id))
    expect(options).not.toContain('in-development')
    expect(options).not.toContain('done')
    wrapper.unmount()
  })

  it('usa o primeiro initial como default do select de status', async () => {
    const wrapper = await openModal()

    expect(wrapper.find('#create-status').element.value).toBe('to-develop')
    wrapper.unmount()
  })

  it('com múltiplos initial, mantém o primeiro como default', async () => {
    seed.statuses = [...seed.statuses, { id: 'fast-track', workflowId: 'standard', name: 'Fast track', type: 'initial' }]
    Object.assign(boardStore.state, { statuses: seed.statuses })
    const wrapper = await openModal()

    expect(wrapper.find('#create-status').element.value).toBe('to-develop')
    wrapper.unmount()
  })

  it('summary vazio gera erro acessível, devolve o foco e não chama createIssue', async () => {
    const wrapper = await openModal()

    await createButton(wrapper).trigger('click')
    await nextTick()

    const alert = wrapper.find('#create-summary-error')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(wrapper.find('#create-summary').attributes('aria-invalid')).toBe('true')
    expect(document.activeElement.id).toBe('create-summary')
    expect(api.createIssue).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('cria a issue com statusId initial, type e assignee escolhidos e sem columnId', async () => {
    const wrapper = await openModal()
    await wrapper.find('#create-summary').setValue('Nova task do contrato')
    await wrapper.find('#create-description').setValue('Descrição da nova task')
    await wrapper.find('#create-type').setValue('task')
    await wrapper.find('#create-assignee').setValue('mai')

    await createButton(wrapper).trigger('click')
    await flushPromises()

    expect(api.createIssue).toHaveBeenCalledTimes(1)
    const payload = api.createIssue.mock.calls[0][0]
    expect(payload.statusId).toBe('to-develop')
    expect(payload.typeId).toBe('task')
    expect(payload.assigneeId).toBe('mai')
    expect(payload).not.toHaveProperty('columnId')
    expect(wrapper.find('.new-task-modal').exists()).toBe(false)
    wrapper.unmount()
  })

  it('erro 422 field=statusId mostra a mensagem do servidor em alerta e mantém o modal aberto', async () => {
    api.createIssue.mockRejectedValue(Object.assign(new Error('a nova task precisa nascer em um status inicial'), { field: 'statusId' }))
    const wrapper = await openModal()
    await wrapper.find('#create-summary').setValue('Nova task do contrato')

    await createButton(wrapper).trigger('click')
    await flushPromises()

    const alert = wrapper.find('#create-status-error')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toBe('a nova task precisa nascer em um status inicial')
    const select = wrapper.find('#create-status')
    expect(select.attributes('aria-invalid')).toBe('true')
    expect(select.attributes('aria-describedby')).toBe('create-status-error')
    expect(wrapper.find('.new-task-modal').exists()).toBe(true)
    wrapper.unmount()
  })

  it('sem initial: exibe o aviso, desabilita select e Create e não chama createIssue', async () => {
    seed.statuses.find((status) => status.id === 'to-develop').type = 'transitional'
    Object.assign(boardStore.state, { statuses: seed.statuses })
    const wrapper = await openModal()
    await wrapper.find('#create-summary').setValue('Nova task do contrato')

    expect(wrapper.find('#create-no-initial').attributes('role')).toBe('alert')
    expect(wrapper.find('#create-status').attributes('disabled')).toBeDefined()
    expect(createButton(wrapper).attributes('disabled')).toBeDefined()

    await createButton(wrapper).trigger('click')
    await flushPromises()
    expect(api.createIssue).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
