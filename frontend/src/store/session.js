import { reactive } from 'vue'
import { request, token, setToken } from '../api/client.js'

const state = reactive({
  login: '',
  displayName: '',
  avatar: '',
  avatarColor: '',
  signedIn: false,
  isAdmin: false,
  workspaceId: null,
  workspaces: [],
})

function applyUser(user) {
  state.login = user.login
  state.displayName = user.displayName
  state.avatar = user.avatar ?? ''
  state.avatarColor = user.avatarColor ?? ''
  state.isAdmin = Boolean(user.isAdmin)
}

async function applyWorkspaces() {
  const payload = await request('GET', '/api/workspaces')
  state.workspaces = payload.workspaces
  state.workspaceId = state.workspaces[0]?.id ?? null
}

export const session = {
  get login() {
    return state.login
  },
  set login(value) {
    state.login = value
  },
  get displayName() {
    return state.displayName
  },
  set displayName(value) {
    state.displayName = value
  },
  get avatar() {
    return state.avatar
  },
  set avatar(value) {
    state.avatar = value
  },
  get avatarColor() {
    return state.avatarColor || '#333333'
  },
  get signedIn() {
    return state.signedIn
  },
  get isAdmin() {
    return state.isAdmin
  },
  get workspaceId() {
    return state.workspaceId
  },
  get workspaces() {
    return state.workspaces
  },
  get label() {
    return state.displayName || state.login
  },
  get initial() {
    return (state.displayName || state.login || '?').charAt(0).toUpperCase()
  },
  async load() {
    if (!token()) {
      state.signedIn = false
      return state
    }
    try {
      const me = await request('GET', '/api/me')
      applyUser(me.user)
      await applyWorkspaces()
      state.signedIn = true
    } catch {
      setToken(null)
      state.signedIn = false
    }
    return state
  },
  async signIn(login, password) {
    if (!login || !password) {
      throw new Error('Login and password are required')
    }
    const payload = await request('POST', '/api/auth/login', { login, password })
    setToken(payload.token)
    applyUser(payload.user)
    await applyWorkspaces()
    state.signedIn = true
    return payload.user
  },
  async updateProfile({ displayName, avatar }) {
    const payload = await request('PATCH', '/api/me', {
      ...(displayName !== undefined ? { displayName } : {}),
      ...(avatar !== undefined ? { avatar } : {}),
    })
    applyUser(payload.user)
    return payload.user
  },
  async switchWorkspace(workspaceId) {
    if (!state.workspaces.some((workspace) => workspace.id === workspaceId)) {
      throw new Error('Unknown workspace')
    }
    state.workspaceId = workspaceId
  },
  async signOut() {
    if (token()) {
      try {
        await request('POST', '/api/auth/logout')
      } catch {
        setToken(null)
      }
    }
    setToken(null)
    state.signedIn = false
  },
}
