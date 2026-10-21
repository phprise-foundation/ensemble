import { reactive } from 'vue'

const LIMIT = 3
const DURATIONS = { success: 4000, info: 4000, error: 6000, warning: 6000 }

const state = reactive({ items: [] })
let counter = 0

function push(kind, text) {
  counter += 1
  const id = counter
  state.items.push({ id, kind, text })
  if (state.items.length > LIMIT) state.items.shift()
  setTimeout(() => dismiss(id), DURATIONS[kind])
}

function dismiss(id) {
  const index = state.items.findIndex((item) => item.id === id)
  if (index !== -1) state.items.splice(index, 1)
}

export const toast = {
  get items() {
    return state.items
  },
  success(text) {
    push('success', text)
  },
  error(text) {
    push('error', text)
  },
  info(text) {
    push('info', text)
  },
  warning(text) {
    push('warning', text)
  },
  dismiss,
}
