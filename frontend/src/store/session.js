import { reactive } from 'vue'
import { api } from '../api/index.js'

const state = reactive({ login: '', displayName: '', avatar: '', avatarColor: '', signedIn: true })

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
  get avatarColor() {
    return state.avatarColor || '#333333'
  },
  get signedIn() {
    return state.signedIn
  },
  get label() {
    return state.displayName || state.login
  },
  get initial() {
    return (state.displayName || state.login || '?').charAt(0).toUpperCase()
  },
  async load() {
    const user = await api.fetchUser()
    state.login = user.login
    state.displayName = user.displayName
    state.avatar = user.avatar ?? ''
    state.avatarColor = user.avatarColor ?? ''
  },
  async updateProfile({ displayName, avatar }) {
    const changes = { displayName }
    if (avatar !== undefined) {
      changes.avatar = avatar
    }
    const user = await api.updateUser(changes)
    state.login = user.login
    state.displayName = user.displayName
    state.avatar = user.avatar ?? ''
    state.avatarColor = user.avatarColor ?? ''
    return user
  },
  signOut() {
    state.signedIn = false
  },
  signIn() {
    state.signedIn = true
  },
}
