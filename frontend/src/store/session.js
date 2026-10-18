import { reactive } from 'vue'
import { api } from '../api/index.js'

const state = reactive({ login: '', displayName: '', signedIn: true })

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
  },
  async rename(name) {
    const user = await api.updateUser({ displayName: name })
    state.login = user.login
    state.displayName = user.displayName
    return user
  },
  signOut() {
    state.signedIn = false
  },
  signIn() {
    state.signedIn = true
  },
}
