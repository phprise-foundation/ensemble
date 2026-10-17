import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'

const { createEntity, updateEntity } = vi.hoisted(() => ({
  createEntity: vi.fn(),
  updateEntity: vi.fn(),
}))

vi.mock('../src/api/index.js', () => ({
  api: {
    createEntity: (collection, data) => createEntity(collection, data),
    updateEntity: (collection, id, data) => updateEntity(collection, id, data),
  },
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

import { boardStore } from '../src/store/board.js'
import AppSidebar from '../src/components/AppSidebar.vue'

let wrapper

function mountSidebar() {
  wrapper = mount(AppSidebar, {
    attachTo: document.body,
    global: { stubs: { RouterLink: 'a', AppIcon: 'span' } },
  })
  return wrapper
}

function navButton(title) {
  return wrapper.findAll('button').find((button) => button.attributes('title') === title)
}

function fieldInput(label) {
  return Array.from(document.body.querySelectorAll('.modal-body label')).find(
    (element) => element.querySelector('span')?.textContent === label,
  )?.querySelector('input, textarea, select')
}

async function fill(label, value) {
  const input = fieldInput(label)
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  input.dispatchEvent(new Event('change', { bubbles: true }))
  await nextTick()
}

async function save() {
  await Array.from(document.body.querySelectorAll('.modal-footer button'))
    .find((button) => button.textContent.trim() === 'Save')
    .click()
  await flushPromises()
  await nextTick()
}

beforeEach(() => {
  createEntity.mockImplementation(async (collection, data) => ({ ...data, id: data.id || `${collection}-generated` }))
  updateEntity.mockImplementation(async (collection, id, data) => ({ ...data, id }))
  boardStore.state.workspaces = [{ id: 'ws', name: 'Workspace' }]
  boardStore.state.teams = []
  boardStore.state.workflows = []
  boardStore.state.sources = []
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('creation derives the verb from the fact, not from a slug', () => {
  it('creates a new team with POST, leaving the id empty', async () => {
    mountSidebar()

    await navButton('New team').trigger('click')
    await nextTick()
    await fill('Name', 'Design')

    expect(fieldInput('ID (optional)').value).toBe('')

    await save()

    expect(createEntity).toHaveBeenCalledWith('teams', expect.objectContaining({ id: '', name: 'Design', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
  })

  it('updates an existing team with PATCH, keeping its id', async () => {
    boardStore.state.teams = [{ id: 'time-a', name: 'Time A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit team').trigger('click')
    await nextTick()
    await fill('Name', 'Time A Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('teams', 'time-a', expect.objectContaining({ id: 'time-a', name: 'Time A Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
  })

  it('creates a new workflow with POST, leaving the id empty', async () => {
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Development')
    await save()

    expect(createEntity).toHaveBeenCalledWith('workflows', expect.objectContaining({ id: '', name: 'Development', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
  })

  it('updates an existing workflow with PATCH, keeping its id', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Flow A Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('workflows', 'flow-a', expect.objectContaining({ id: 'flow-a', name: 'Flow A Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
  })
})
