import { describe, it, expect, vi, beforeEach } from 'vitest'

const mocks = vi.hoisted(() => ({ real: false, tokenValue: null, request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  isRealBackend: () => mocks.real,
  token: () => mocks.tokenValue,
  setToken: (value) => {
    mocks.tokenValue = value
  },
  request: (...args) => mocks.request(...args),
}))

vi.mock('../src/api/index.js', () => ({
  api: {
    fetchUser: () => mocks.request('GET', '/api/user'),
    workspaces: () => mocks.request('GET', '/api/workspaces'),
    snapshot: (workspaceId) => mocks.request('GET', `/api/workspaces/${workspaceId}/snapshot`),
    fetchWorkspace: () => mocks.request('GET', '/api/workspace'),
    fetchIssues: () => mocks.request('GET', '/api/issues'),
    createEntity: (collection, data) => mocks.request('POST', `/api/${collection}`, data),
    updateEntity: (collection, id, data) => mocks.request('PATCH', `/api/${collection}/${id}`, data),
    deleteEntity: (collection, id) => mocks.request('DELETE', `/api/${collection}/${id}`),
  },
  request: (...args) => mocks.request(...args),
}))

const USER = { login: 'admin', displayName: 'Admin' }
const WORKSPACES = { workspaces: [{ id: 'w1', name: 'One' }] }
const SNAPSHOT = {
  workspaces: WORKSPACES.workspaces,
  workflows: [],
  issueTypes: [],
  statuses: [],
  transitions: [],
  boards: [],
  columns: [],
  issues: [],
  teams: [],
  roles: [],
  profiles: [],
  agents: [],
}

const boot = async () => {
  const [{ session }, { boardStore }] = await Promise.all([
    import('../src/store/session.js'),
    import('../src/store/board.js'),
  ])
  mocks.request.mockImplementation(async (method, path) => {
    if (path === '/api/me') {
      return { user: USER }
    }
    if (path === '/api/workspaces') {
      return WORKSPACES
    }
    if (path === '/api/workspaces/w1/snapshot') {
      return JSON.parse(JSON.stringify(SNAPSHOT))
    }
    throw new Error(`unexpected ${method} ${path}`)
  })
  await session.load()
  return { session, boardStore }
}

beforeEach(() => {
  vi.resetModules()
  mocks.real = true
  mocks.tokenValue = 'stored'
  mocks.request = vi.fn()
})

describe('real entity routing', () => {
  it('posts statuses under the workflow and unwraps the entity', async () => {
    const { boardStore } = await boot()
    mocks.request.mockImplementation(async (method, path, body) => {
      if (method === 'POST' && path === '/api/workspaces/w1/workflows/wf9/statuses') {
        expect(body).toEqual({ name: 'Doing', type: 'transitional', issueTypeId: 't1' })
        return { status: { id: 's1', workflowId: 'wf9', issueTypeId: 't1', name: 'Doing', type: 'transitional', roleIds: [] } }
      }
      if (path === '/api/workspaces/w1/snapshot') {
        return { ...SNAPSHOT, statuses: [{ id: 's1', workflowId: 'wf9', name: 'Doing', type: 'transitional' }] }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })

    const saved = await boardStore.saveEntity('statuses', { workflowId: 'wf9', id: '', name: 'Doing', type: 'transitional', issueTypeId: 't1' })

    expect(saved.id).toBe('s1')
    expect(boardStore.state.statuses).toHaveLength(1)
  })

  it('maps legacy transition keys onto the real payload', async () => {
    const { boardStore } = await boot()
    mocks.request.mockImplementation(async (method, path, body) => {
      if (method === 'POST' && path === '/api/workspaces/w1/workflows/wf9/transitions') {
        expect(body).toEqual({ fromStatusId: 's1', toStatusId: 's2', actor: 'user' })
        return { transition: { id: 'tr1', workflowId: 'wf9', fromStatusId: 's1', toStatusId: 's2', actor: 'user' } }
      }
      if (path === '/api/workspaces/w1/snapshot') {
        return {
          ...SNAPSHOT,
          transitions: [{ id: 'tr1', workflowId: 'wf9', fromStatusId: 's1', toStatusId: 's2', actor: 'user' }],
        }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })

    const saved = await boardStore.saveEntity('transitions', { workflowId: 'wf9', from: 's1', to: 's2', type: 'user' })

    expect(saved.from).toBe('s1')
    expect(saved.to).toBe('s2')
    expect(saved.type).toBe('user')
  })

  it('deletes by the path stored on the entity', async () => {
    const { boardStore } = await boot()
    mocks.request.mockImplementation(async (method, path) => {
      if (method === 'DELETE' && path === '/api/workspaces/w1/workflows/wf9/statuses/s1') {
        return { ok: true }
      }
      if (path === '/api/workspaces/w1/snapshot') {
        return SNAPSHOT
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    boardStore.state.statuses.push({ id: 's1', workflowId: 'wf9', name: 'Doing', type: 'transitional' })

    await boardStore.removeEntity('statuses', 's1')

    expect(boardStore.state.statuses).toHaveLength(0)
  })

  it('refuses collections the backend does not model', async () => {
    const { boardStore } = await boot()

    await expect(boardStore.saveEntity('experiences', { name: 'x' })).rejects.toThrow('not available on the real backend')
  })

  it('strips legacy-only board keys from the payload', async () => {
    const { boardStore } = await boot()
    mocks.request.mockImplementation(async (method, path, body) => {
      if (method === 'POST' && path === '/api/workspaces/w1/boards') {
        expect(body).toEqual({ name: 'Main', workflowId: 'wf9' })
        return { board: { id: 'b1', name: 'Main', workflowId: 'wf9' } }
      }
      if (path === '/api/workspaces/w1/snapshot') {
        return { ...SNAPSHOT, boards: [{ id: 'b1', name: 'Main', workflowId: 'wf9' }] }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })

    await boardStore.saveEntity('boards', { id: '', name: 'Main', workflowId: 'wf9', filters: { statusIds: [] }, columns: [] })

    expect(boardStore.state.boards).toHaveLength(1)
  })
})
