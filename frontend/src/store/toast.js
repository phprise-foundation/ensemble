import { reactive } from 'vue'

const DURATION_MS = { success: 4000, info: 4000, error: 6000, warning: 6000 }
const MAX_VISIBLE = 3

const state = reactive({
  items: [],
})
let nextId = 1
const timers = new Map()

function removeToast(id) {
  const index = state.items.findIndex((item) => item.id === id)
  if (index !== -1) {
    state.items.splice(index, 1)
  }
  if (timers.has(id)) {
    clearTimeout(timers.get(id))
    timers.delete(id)
  }
}

function push(kind, text) {
  if (state.items.length >= MAX_VISIBLE) {
    removeToast(state.items[0].id)
  }
  const toast = { id: nextId++, kind, text, duration: DURATION_MS[kind] }
  state.items.push(toast)
  timers.set(toast.id, setTimeout(() => removeToast(toast.id), toast.duration))
}

export const toastStore = {
  state,
  success(text) {
    push('success', text)
  },
  info(text) {
    push('info', text)
  },
  warning(text) {
    push('warning', text)
  },
  error(text) {
    push('error', text)
  },
}

export const toast = {
  success: toastStore.success,
  info: toastStore.info,
  warning: toastStore.warning,
  error: toastStore.error,
  remove: removeToast,
}
