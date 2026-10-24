import { reactive } from 'vue'
import { api, request } from '../api/index.js'
import { token, setToken } from '../api/client.js'

const realBackend = Boolean(import.meta.env?.VITE_API_URL)

const state = reactive({
  login: '',
  displayName: '',
  avatar: '',
  avatarColor: '',
  signedIn: true,
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
  const payload = await api.workspaces()
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
    if (realBackend && token()) {
      try {
        return await this.restore()
      } catch {
        setToken(null)
        state.signedIn = false
        return state
      }
    }
    const user = await api.fetchUser()
    applyUser(user)
  },
  async restore() {
    const me = await request('GET', '/api/me')
    applyUser(me.user)
    await applyWorkspaces()
    state.signedIn = true
  },
  async signIn(login, password) {
    if (!realBackend && !token()) {
      state.signedIn = true
      return state
    }
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
    const changes = { displayName }
    if (avatar !== undefined) {
      changes.avatar = avatar
    }
    const user = await api.updateUser(changes)
    applyUser(user)
    return user
  },
  async signOut() {
    setToken(null)
    state.signedIn = false
  },
}
