import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'info',
  role: 'dialog',
  scrimCloses: false,
  resolver: null,
})

function applyOptions(options) {
  state.title = options.title
  state.message = options.message ?? ''
  state.confirmLabel = options.confirmLabel ?? 'Confirm'
  state.cancelLabel = options.cancelLabel ?? 'Cancel'
  state.variant = options.variant ?? 'info'
  state.scrimCloses = options.scrimCloses ?? false
  state.role = options.danger ? 'alertdialog' : 'dialog'
}

function finish(result) {
  if (!state.open) {
    return
  }
  state.open = false
  const resolve = state.resolver
  state.resolver = null
  resolve?.(result)
}

export const dialog = {
  confirmChoice() {
    finish(true)
  },
  refuse() {
    finish(false)
  },
}

export const dialogStore = {
  state,
  confirm(options = {}) {
    if (state.open) {
      return Promise.resolve(false)
    }
    applyOptions(options)
    return new Promise((resolve) => {
      state.resolver = resolve
      state.open = true
    })
  },
  alert(options = {}) {
    return dialogStore.confirm({
      ...options,
      confirmLabel: options.confirmLabel ?? 'OK',
      cancelLabel: '',
      scrimCloses: options.scrimCloses ?? true,
    }).then(() => undefined)
  },
}

const dialogState = state
export default dialogState
