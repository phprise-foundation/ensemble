import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'

const client = vi.hoisted(() => ({ tokenValue: 't', request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  token: () => client.tokenValue,
  setToken: (value) => {
    client.tokenValue = value
  },
  request: (...args) => client.request(...args),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: {} }),
  useRouter: () => ({ afterEach: () => {} }),
}))

import { boardStore } from '../src/store/board.js'
import { session } from '../src/store/session.js'
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

function calls(method, suffix) {
  return client.request.mock.calls.filter(([verb, path]) => verb === method && path.includes(suffix))
}

function postedBody(suffix) {
  return calls('POST', suffix)[0]?.[2]
}

function expectNoExistingFlag() {
  for (const call of client.request.mock.calls) {
    if (call[2] !== undefined) {
      expect(call[2]).not.toHaveProperty('__existing')
    }
  }
}

beforeEach(async () => {
  client.request.mockImplementation(async (method, path, body) => {
    if (path === '/api/me') {
      return { user: { login: 'admin', displayName: 'Admin' } }
    }
    if (path === '/api/workspaces') {
      return { workspaces: [{ id: 'ws', name: 'Workspace' }] }
    }
    if (path === '/api/workspaces/ws/snapshot') {
      return {
        workspaces: [{ id: 'ws', name: 'Workspace' }],
        workflows: boardStore.state.workflows,
        issueTypes: boardStore.state.issueTypes,
        statuses: boardStore.state.statuses,
        transitions: boardStore.state.transitions,
        boards: boardStore.state.boards,
        columns: boardStore.state.columns,
        issues: boardStore.state.issues,
        teams: boardStore.state.teams,
        roles: boardStore.state.roles,
        profiles: boardStore.state.profiles,
        agents: boardStore.state.agents,
      }
    }
    if (method === 'POST') {
      return { ...(body ?? {}), id: body?.id || 'generated' }
    }
    throw new Error(`unexpected ${method} ${path}`)
  })
  await session.load()
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
  it('creates a new board via POST to the workspace boards path', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('New board').trigger('click')
    await nextTick()
    await fill('Name', 'Alpha')
    await save()

    expect(calls('POST', '/api/workspaces/ws/boards')).toHaveLength(1)
    expect(postedBody('/boards')).toEqual(expect.objectContaining({ name: 'Alpha', workflowId: 'flow-a' }))
    expect(calls('PATCH', '/boards')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('creates a new workflow via POST', async () => {
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Development')
    await save()

    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(1)
    expect(postedBody('/workflows')).toEqual(expect.objectContaining({ name: 'Development' }))
    expect(calls('PATCH', '/workflows')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('creates a new team via POST', async () => {
    mountSidebar()

    await navButton('New team').trigger('click')
    await nextTick()
    await fill('Name', 'Design')
    await save()

    expect(calls('POST', '/api/workspaces/ws/teams')).toHaveLength(1)
    expect(postedBody('/teams')).toEqual(expect.objectContaining({ name: 'Design' }))
    expect(calls('PATCH', '/teams')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('copies template children via POST with server ids and skips wildcard transitions', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    boardStore.state.statuses = [
      { id: 'up', workflowId: 'flow-a', name: 'Up', type: 'normal', color: '#ff0000', roleIds: [] },
      { id: 'down', workflowId: 'flow-a', name: 'Down', type: 'normal', color: '#0000ff', roleIds: [] },
    ]
    boardStore.state.transitions = [{ workflowId: 'flow-a', fromStatusId: '*', toStatusId: 'up', actor: 'user' }]
    boardStore.state.issueTypes = [{ id: 'task', workflowId: 'flow-a', name: 'Task', level: 1 }]
    client.request.mockImplementation(async (method, path, body) => {
      if (path === '/api/workspaces/ws/snapshot') {
        return {
          workspaces: [{ id: 'ws', name: 'Workspace' }],
          workflows: boardStore.state.workflows,
          issueTypes: boardStore.state.issueTypes,
          statuses: boardStore.state.statuses,
          transitions: boardStore.state.transitions,
          boards: [],
          columns: [],
          issues: [],
          teams: [],
          roles: [],
          profiles: [],
          agents: [],
        }
      }
      if (method === 'POST' && path === '/api/workspaces/ws/workflows') {
        return { workflow: { id: 'flow-new', name: body?.name } }
      }
      if (method === 'POST' && path.endsWith('/statuses')) {
        return { status: { id: `st-${body?.name}`, workflowId: 'flow-new', name: body?.name, type: body?.type } }
      }
      if (method === 'POST' && path.endsWith('/issue-types')) {
        return { issueType: { id: `it-${body?.name}`, workflowId: 'flow-new', name: body?.name, level: body?.level } }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Flow New')
    await fill('Start from a template (optional)', 'flow-a')
    await save()

    expect(calls('POST', '/workflows/flow-new/statuses')).toHaveLength(2)
    expect(calls('POST', '/workflows/flow-new/statuses').map((call) => call[2].name)).toEqual(['Up', 'Down'])
    expect(calls('POST', '/workflows/flow-new/transitions')).toHaveLength(0)
    expect(calls('POST', '/workflows/flow-new/issue-types')).toHaveLength(1)
    expect(calls('PATCH', '/api/workspaces/ws')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('edits an existing workflow via PATCH, keeping its id in the path and out of the body', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Flow A Renomeado')
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/workflows/flow-a')).toHaveLength(1)
    expect(client.request.mock.calls.find(([method, path]) => method === 'PATCH' && path.endsWith('/workflows/flow-a'))[2]).toEqual({ name: 'Flow A Renomeado' })
    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('edits an existing team via PATCH', async () => {
    boardStore.state.teams = [{ id: 'time-a', name: 'Time A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit team').trigger('click')
    await nextTick()
    await fill('Name', 'Time A Renomeado')
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/teams/time-a')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/teams')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('edits an existing board via PATCH', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    boardStore.state.boards = [{ id: 'abcd', name: 'Alpha', workflowId: 'flow-a', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit board').trigger('click')
    await nextTick()
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/boards/abcd')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/boards')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('edits an existing repository (source) via PATCH', async () => {
    boardStore.state.sources = [{ id: 'src-a', name: 'Projeto A', path: '/home/you/src/projeto-a', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit source').trigger('click')
    await nextTick()
    await fill('Directory (local path)', '/home/you/src/projeto-b')
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/repositories/src-a')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/repositories')).toHaveLength(0)
    expectNoExistingFlag()
  })

  it('creates a new team with an empty id via POST as before (guard)', async () => {
    mountSidebar()

    await navButton('New team').trigger('click')
    await nextTick()
    await fill('Name', 'Design')
    await save()

    expect(calls('POST', '/api/workspaces/ws/teams')).toHaveLength(1)
    expect(calls('PATCH', '/api/workspaces/ws/teams')).toHaveLength(0)
  })
})

describe('contraexemplos: o contrato de verbo falha nominalmente, nunca silencioso', () => {
  it('a draft carrying an existing key without the fact is born new via POST, never silently PATCHed', async () => {
    await boardStore.saveEntity('workflows', { id: 'flow-a', name: 'Mesmo Id De Existente', __existing: false })

    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(1)
    expect(calls('PATCH', '/api/workspaces/ws/workflows')).toHaveLength(0)
    expect(client.request.mock.calls.find(([method]) => method === 'POST')[2]).not.toHaveProperty('__existing')
  })

  it('a draft with the fact and no persisted id chooses PATCH and does not swallow the 404', async () => {
    client.request.mockRejectedValueOnce(new Error('workflows not found'))
    await expect(boardStore.saveEntity('workflows', { id: '', name: 'Ficcional', __existing: true })).rejects.toThrow('workflows not found')

    expect(calls('PATCH', '/api/workspaces/ws/workflows')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(0)
    expect(client.request.mock.calls.find(([method]) => method === 'PATCH')[2]).not.toHaveProperty('__existing')
  })
})
