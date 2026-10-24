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
  useRoute: () => ({}),
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

beforeEach(async () => {
  client.request.mockImplementation(async (method, path) => {
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
        issueTypes: [],
        statuses: [],
        transitions: [],
        boards: boardStore.state.boards,
        columns: [],
        issues: [],
        teams: boardStore.state.teams,
        roles: [],
        profiles: [],
        agents: [],
      }
    }
    if (method === 'POST') {
      return { ok: true }
    }
    throw new Error(`unexpected ${method} ${path}`)
  })
  await session.load()
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

    expect(calls('POST', '/api/workspaces/ws/teams')).toHaveLength(1)
    expect(calls('PATCH', '/api/workspaces/ws/teams')).toHaveLength(0)
  })

  it('updates an existing team with PATCH, keeping its id in the path', async () => {
    boardStore.state.teams = [{ id: 'time-a', name: 'Time A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit team').trigger('click')
    await nextTick()
    await fill('Name', 'Time A Renomeado')
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/teams/time-a')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/teams')).toHaveLength(0)
  })

  it('creates a new workflow with POST, leaving the id empty', async () => {
    mountSidebar()

    await navButton('New workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Development')
    await save()

    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(1)
    expect(calls('PATCH', '/api/workspaces/ws/workflows')).toHaveLength(0)
  })

  it('updates an existing workflow with PATCH, keeping its id in the path', async () => {
    boardStore.state.workflows = [{ id: 'flow-a', name: 'Flow A', workspaceId: 'ws' }]
    mountSidebar()

    await navButton('Edit workflow').trigger('click')
    await nextTick()
    await fill('Name', 'Flow A Renomeado')
    await save()

    expect(calls('PATCH', '/api/workspaces/ws/workflows/flow-a')).toHaveLength(1)
    expect(calls('POST', '/api/workspaces/ws/workflows')).toHaveLength(0)
  })
})
