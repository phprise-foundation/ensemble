import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'

const { createEntity, updateEntity, routeParams } = vi.hoisted(() => ({
  createEntity: vi.fn(),
  updateEntity: vi.fn(),
  routeParams: { params: {} },
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
  useRoute: () => ({ params: routeParams.params, query: {} }),
  useRouter: () => ({ afterEach: () => {} }),
}))

import { boardStore } from '../src/store/board.js'
import AppSidebar from '../src/components/AppSidebar.vue'
import AgentsPage from '../src/views/AgentsPage.vue'
import IssueTypesPage from '../src/views/IssueTypesPage.vue'

let wrapper

function mountSidebar() {
  wrapper = mount(AppSidebar, {
    attachTo: document.body,
    global: { stubs: { RouterLink: 'a', AppIcon: 'span' } },
  })
  return wrapper
}

function mountPage(component) {
  wrapper = mount(component, {
    attachTo: document.body,
    global: { stubs: { RouterLink: 'a', AppIcon: 'span' } },
  })
  return wrapper
}

function navButton(title) {
  return wrapper.findAll('button').find((button) => button.attributes('title') === title)
}

function editButton() {
  return wrapper.findAll('button').find((button) => button.attributes('title') === 'Edit')
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

function expectNoExistingFlag() {
  for (const call of createEntity.mock.calls) {
    expect(call[1]).not.toHaveProperty('__existing')
  }
  for (const call of updateEntity.mock.calls) {
    expect(call[2]).not.toHaveProperty('__existing')
  }
}

beforeEach(() => {
  createEntity.mockImplementation(async (collection, data) => ({ ...data, id: data.id || `${collection}-generated` }))
  updateEntity.mockImplementation(async (collection, id, data) => ({ ...data, id }))
  routeParams.params = {}
  boardStore.state.workspaces = [{ id: 'ws', name: 'Workspace' }]
  boardStore.state.boards = []
  boardStore.state.teams = []
  boardStore.state.workflows = []
  boardStore.state.statuses = []
  boardStore.state.transitions = []
  boardStore.state.issueTypes = []
  boardStore.state.sources = []
  boardStore.state.agents = []
  boardStore.state.roles = []
  boardStore.state.profiles = []
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('the verb is born from the fact (__existing), not from the id field', () => {
  it('creates a new board with a typed id via POST (red: currently PATCH)', async () => {
    mountSidebar()

    await navButton('New board').trigger('click')
    await nextTick()
    await fill('ID (3 to 6 letters)', 'abcd')
    await fill('Name', 'Alpha')
    await save()

    expect(createEntity).toHaveBeenCalledWith('boards', expect.objectContaining({ id: 'abcd', name: 'Alpha', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('creates a new workflow with a typed id via POST (red: currently PATCH)', async () => {
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('ID', 'devflow')
    await fill('Name', 'Development')
    await save()

    expect(createEntity).toHaveBeenCalledWith('workflows', expect.objectContaining({ id: 'devflow', name: 'Development', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('creates a new team with a typed id via POST (red: currently PATCH)', async () => {
    mountSidebar()

    await navButton('New team').trigger('click')
    await nextTick()
    await fill('Name', 'Design')
    await fill('ID (optional)', 'tg-alpha')
    await save()

    expect(createEntity).toHaveBeenCalledWith('teams', expect.objectContaining({ id: 'tg-alpha', name: 'Design', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('copies template children via POST with pre-generated ids, no PATCH (red: currently PATCH)', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    boardStore.state.statuses = [
      { id: 'up', workflowId: 'flow-a', name: 'Up', type: 'normal', color: '#ff0000', roleIds: [] },
      { id: 'down', workflowId: 'flow-a', name: 'Down', type: 'normal', color: '#0000ff', roleIds: [] },
    ]
    boardStore.state.transitions = [{ workflowId: 'flow-a', from: '*', to: 'up', type: 'user' }]
    boardStore.state.issueTypes = [{ id: 'task', workflowId: 'flow-a', name: 'Task', level: 1, fields: [{ key: 'summary', label: 'Summary', type: 'text', required: true, system: true }] }]
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('ID', 'flow-new')
    await fill('Name', 'Flow New')
    await fill('Start from a template (optional)', 'flow-a')
    await save()

    expect(createEntity).toHaveBeenCalledWith('workflows', expect.objectContaining({ id: 'flow-new', name: 'Flow New', workspaceId: 'ws' }))
    expect(createEntity).toHaveBeenCalledWith('statuses', expect.objectContaining({ id: 'flow-new-up', workflowId: 'flow-new', name: 'Up' }))
    expect(createEntity).toHaveBeenCalledWith('transitions', expect.objectContaining({ workflowId: 'flow-new', from: '*', to: 'flow-new-up' }))
    expect(createEntity).toHaveBeenCalledWith('issueTypes', expect.objectContaining({ id: 'flow-new-task', name: 'Task' }))
    expect(updateEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edit workflow modal does not expose an editable id (red: currently exposes ID)', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit workflow').trigger('click')
    await nextTick()

    const idLabel = Array.from(document.body.querySelectorAll('.modal-body label')).find(
      (element) => element.querySelector('span')?.textContent === 'ID',
    )
    expect(idLabel).toBeUndefined()
  })

  it('edits an existing workflow via PATCH, keeping its id and stripping the flag', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Flow A Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('workflows', 'flow-a', expect.objectContaining({ id: 'flow-a', name: 'Flow A Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edits an existing team via PATCH, keeping its id and stripping the flag', async () => {
    boardStore.state.teams = [{ id: 'time-a', name: 'Time A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit team').trigger('click')
    await nextTick()
    await fill('Name', 'Time A Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('teams', 'time-a', expect.objectContaining({ id: 'time-a', name: 'Time A Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edits an existing board via PATCH, keeping its id and stripping the flag', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    boardStore.state.boards = [{ id: 'abcd', name: 'Alpha', workflowId: 'flow-a', workspaceId: 'ws', filters: { statusIds: [] }, columns: [] }]
    mountSidebar()

    await navButton('Edit board').trigger('click')
    await nextTick()
    await save()

    expect(updateEntity).toHaveBeenCalledWith('boards', 'abcd', expect.objectContaining({ id: 'abcd', name: 'Alpha' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edits an existing source via PATCH, keeping its id and stripping the flag', async () => {
    boardStore.state.sources = [{ id: 'src-a', name: 'Projeto A', path: '/home/you/src/projeto-a', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit source').trigger('click')
    await nextTick()
    await fill('Directory (local path)', '/home/you/src/projeto-b')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('sources', 'src-a', expect.objectContaining({ id: 'src-a', path: '/home/you/src/projeto-b' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edits an existing agent via its page with PATCH after openEdit marks __existing', async () => {
    routeParams.params = { teamId: 'team-a' }
    boardStore.state.agents = [{ id: 'agent-a', teamId: 'team-a', name: 'Bot A', whoAmI: '', age: null, maritalStatus: 'Single', roleIds: [], profileId: 'profile-a', portrait: '', avatarColor: '#4c9aff' }]
    boardStore.state.roles = []
    boardStore.state.profiles = [{ id: 'profile-a', teamId: 'team-a', name: 'Profile A' }]
    mountPage(AgentsPage)

    await editButton().trigger('click')
    await nextTick()
    await fill('Name', 'Bot A Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('agents', 'agent-a', expect.objectContaining({ id: 'agent-a', name: 'Bot A Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('edits an existing issue type via its page with PATCH after openEdit marks __existing', async () => {
    routeParams.params = { workflowId: 'flow-a' }
    boardStore.state.issueTypes = [{ id: 'task', workflowId: 'flow-a', name: 'Task', level: 1, fields: [{ key: 'summary', label: 'Summary', type: 'text', required: true, system: true }] }]
    mountPage(IssueTypesPage)

    await editButton().trigger('click')
    await nextTick()
    await fill('Name', 'Task Renomeado')
    await save()

    expect(updateEntity).toHaveBeenCalledWith('issueTypes', 'task', expect.objectContaining({ id: 'task', name: 'Task Renomeado' }))
    expect(createEntity).not.toHaveBeenCalled()
    expectNoExistingFlag()
  })

  it('creates a new team with an empty id via POST as before (guard)', async () => {
    mountSidebar()

    await navButton('New team').trigger('click')
    await nextTick()
    await fill('Name', 'Design')
    await save()

    expect(createEntity).toHaveBeenCalledWith('teams', expect.objectContaining({ id: '', name: 'Design', workspaceId: 'ws' }))
    expect(updateEntity).not.toHaveBeenCalled()
  })
})

describe('contraexemplos: o contrato de verbo falha nominalmente, nunca silencioso', () => {
  it('a draft carrying an existing key without the fact is born new via POST, never silently PATCHed', async () => {
    await boardStore.saveEntity('workflows', { id: 'flow-a', name: 'Mesmo Id De Existente', __existing: false })

    expect(createEntity).toHaveBeenCalledWith('workflows', expect.objectContaining({ id: 'flow-a', name: 'Mesmo Id De Existente' }))
    expect(updateEntity).not.toHaveBeenCalled()
    expect(createEntity.mock.calls[0][1]).not.toHaveProperty('__existing')
  })

  it('a draft with the fact and no persisted id chooses PATCH and does not swallow the 404', async () => {
    updateEntity.mockRejectedValueOnce(new Error('workflows not found'))
    await expect(boardStore.saveEntity('workflows', { id: '', name: 'Ficcional', __existing: true })).rejects.toThrow('workflows not found')

    expect(updateEntity).toHaveBeenCalled()
    expect(createEntity).not.toHaveBeenCalled()
    expect(updateEntity.mock.calls[0][2]).not.toHaveProperty('__existing')
  })
})
