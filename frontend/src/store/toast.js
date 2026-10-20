import { reactive } from 'vue'

const state = reactive({
  toasts: [],
})

const MAX_TOASTS = 3
const SHORT_DURATION_MS = 4000
const LONG_DURATION_MS = 6000

let lastId = 0

function removeToast(id) {
  const index = state.toasts.findIndex((candidate) => candidate.id === id)
  if (index !== -1) {
    state.toasts.splice(index, 1)
  }
}

function pushToast(kind, text) {
  lastId += 1
  const id = lastId
  state.toasts.push({ id: id, kind: kind, text: text })

  if (state.toasts.length > MAX_TOASTS) {
    state.toasts.shift()
  }

  const duration = kind === 'error' || kind === 'warning' ? LONG_DURATION_MS : SHORT_DURATION_MS
  setTimeout(() => removeToast(id), duration)
}

export const toast = {
  get toasts() {
    return state.toasts
  },

  success(text) {
    pushToast('success', text)
  },

  error(text) {
    pushToast('error', text)
  },

  info(text) {
    pushToast('info', text)
  },

  warning(text) {
    pushToast('warning', text)
  },

  dismiss(id) {
    removeToast(id)
  },
}
