import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const mocks = vi.hoisted(() => ({
  real: false,
  tokenValue: null,
  request: vi.fn(),
}))

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
    updateUser: (data) => mocks.request('PATCH', '/api/user', data),
    workspaces: () => mocks.request('GET', '/api/workspaces'),
    snapshot: (workspaceId) => mocks.request('GET', `/api/workspaces/${workspaceId}/snapshot`),
    fetchIssues: () => mocks.request('GET', '/api/issues'),
    fetchWorkspace: () => mocks.request('GET', '/api/workspace'),
  },
  request: (...args) => mocks.request(...args),
}))

const loadStores = async () => {
  const [{ session }, { boardStore }] = await Promise.all([
    import('../src/store/session.js'),
    import('../src/store/board.js'),
  ])
  return { session, boardStore }
}

const USER = { login: 'admin', displayName: 'Admin', avatar: '', avatarColor: '#000' }
const WORKSPACES = { workspaces: [{ id: 'w1', name: 'One' }] }

beforeEach(() => {
  vi.resetModules()
  mocks.real = false
  mocks.tokenValue = null
  mocks.request = vi.fn()
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('real backend session', () => {
  it('lands on the gate when there is no stored token', async () => {
    mocks.real = true
    const { session } = await loadStores()

    await session.load()

    expect(session.signedIn).toBe(false)
    expect(mocks.request).not.toHaveBeenCalled()
  })

  it('restores the session and picks the first workspace from the token', async () => {
    mocks.real = true
    mocks.tokenValue = 'stored'
    mocks.request.mockImplementation(async (method, path) => {
      if (path === '/api/me') {
        return { user: USER }
      }
      if (path === '/api/workspaces') {
        return WORKSPACES
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    const { session } = await loadStores()

    await session.load()

    expect(session.signedIn).toBe(true)
    expect(session.workspaceId).toBe('w1')
    expect(session.displayName).toBe('Admin')
  })

  it('clears the token and shows the gate when the token expired', async () => {
    mocks.real = true
    mocks.tokenValue = 'expired'
    const expired = new Error('401 Unauthorized')
    expired.status = 401
    mocks.request.mockRejectedValue(expired)
    const { session } = await loadStores()

    await session.load()

    expect(session.signedIn).toBe(false)
    expect(mocks.tokenValue).toBe(null)
  })

  it('rejects a wrong password without storing a token', async () => {
    mocks.real = true
    const denied = new Error('login does not match')
    denied.status = 401
    mocks.request.mockRejectedValue(denied)
    const { session } = await loadStores()

    await session.load()
    expect(session.signedIn).toBe(false)

    await expect(session.signIn('admin', 'wrong')).rejects.toThrow('login does not match')

    expect(session.signedIn).toBe(false)
    expect(mocks.tokenValue).toBe(null)
  })

  it('signs in through the backend and boots the workspace', async () => {
    mocks.real = true
    mocks.request.mockImplementation(async (method, path) => {
      if (path === '/api/auth/login') {
        return { token: 'fresh', user: USER }
      }
      if (path === '/api/workspaces') {
        return WORKSPACES
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    const { session } = await loadStores()

    const user = await session.signIn('admin', 'admin1234')

    expect(user.login).toBe('admin')
    expect(session.signedIn).toBe(true)
    expect(session.workspaceId).toBe('w1')
    expect(mocks.tokenValue).toBe('fresh')
  })

  it('refuses empty credentials before calling the backend', async () => {
    mocks.real = true
    const { session } = await loadStores()

    await expect(session.signIn('', '')).rejects.toThrow('required')

    expect(mocks.request).not.toHaveBeenCalled()
  })
})

describe('legacy fake session', () => {
  it('keeps the prototype shortcut without touching the backend', async () => {
    const { session } = await loadStores()

    await session.signIn('', '')

    expect(session.signedIn).toBe(true)
    expect(mocks.request).not.toHaveBeenCalled()
  })

  it('boots the fake user on load', async () => {
    mocks.request.mockResolvedValue(USER)
    const { session } = await loadStores()

    await session.load()

    expect(session.signedIn).toBe(true)
    expect(session.login).toBe('admin')
  })
})

describe('real board mode', () => {
  it('boots from the workspace snapshot and scopes writes by key', async () => {
    mocks.real = true
    mocks.tokenValue = 'stored'
    const issue = { id: 'i1', key: 'ONE-1', statusId: 's1' }
    mocks.request.mockImplementation(async (method, path) => {
      if (path === '/api/me') {
        return { user: USER }
      }
      if (path === '/api/workspaces') {
        return WORKSPACES
      }
      if (path === '/api/workspaces/w1/snapshot') {
        return {
          workspaces: WORKSPACES.workspaces,
          workflows: [],
          issueTypes: [],
          statuses: [{ id: 's1', workflowId: 'wf1' }],
          transitions: [],
          boards: [{ id: 'b1', workflowId: 'wf1' }],
          columns: [{ id: 'c1', statusIds: ['s1'] }],
          issues: [issue],
          teams: [],
          roles: [],
          profiles: [],
          agents: [],
        }
      }
      if (path === '/api/workspaces/w1/issues/ONE-1/move') {
        return { issue: { ...issue, statusId: 's2' } }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    const { session, boardStore } = await loadStores()

    await session.load()
    await boardStore.load()

    expect(boardStore.state.boardId).toBe('b1')
    expect(boardStore.state.issues).toHaveLength(1)

    await boardStore.moveIssue('i1', 's2')

    expect(boardStore.state.issues[0].statusId).toBe('s2')
  })
})
