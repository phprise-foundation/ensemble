import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'info',
  scrimCloses: false,
  resolve: null,
})

export const dialog = {
  get open() {
    return state.open
  },
  get title() {
    return state.title
  },
  get message() {
    return state.message
  },
  get confirmLabel() {
    return state.confirmLabel
  },
  get cancelLabel() {
    return state.cancelLabel
  },
  get variant() {
    return state.variant
  },
  get scrimCloses() {
    return state.scrimCloses
  },

  confirm(options = {}) {
    if (state.open) {
      return Promise.resolve(false)
    }

    return new Promise((resolve) => {
      state.title = options.title
      state.message = options.message || ''
      state.confirmLabel = options.confirmLabel || 'Confirm'
      state.cancelLabel = options.cancelLabel || 'Cancel'
      state.variant = options.danger ? 'danger' : 'info'
      state.scrimCloses = options.scrimCloses !== undefined ? options.scrimCloses : false
      state.resolve = resolve
      state.open = true
    })
  },

  alert(options) {
    if (state.open) {
      return Promise.resolve(undefined)
    }

    return new Promise((resolve) => {
      state.title = options.title
      state.message = options.message || ''
      state.confirmLabel = options.confirmLabel || 'OK'
      state.cancelLabel = ''
      state.variant = (options && options.variant) || 'info'
      state.scrimCloses = true
      state.resolve = resolve
      state.open = true
    })
  },

  finish(value) {
    if (state.resolve) {
      const settle = state.resolve
      state.resolve = null
      state.open = false
      settle(value)
    }
  },
}
