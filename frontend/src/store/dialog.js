import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'info',
  danger: false,
  hasCancel: false,
  scrimCloses: false,
  resolve: null,
})

function close(result) {
  const resolve = state.resolve
  state.open = false
  state.resolve = null
  if (resolve) resolve(result)
}

function openBox(options, defaults) {
  if (state.open) return Promise.resolve(defaults.refusal)
  return new Promise((resolve) => {
    state.title = options.title ?? ''
    state.message = options.message ?? ''
    state.confirmLabel = options.confirmLabel ?? 'Confirm'
    state.cancelLabel = options.cancelLabel ?? 'Cancel'
    state.danger = options.danger === true
    state.variant = state.danger ? 'danger' : (options.variant ?? defaults.variant)
    state.hasCancel = defaults.hasCancel
    state.scrimCloses = options.scrimCloses ?? defaults.scrimCloses
    state.resolve = resolve
    state.open = true
  })
}

export const dialog = {
  get state() {
    return state
  },
  confirm(options = {}) {
    return openBox(options, { variant: 'info', hasCancel: true, scrimCloses: false, refusal: false })
  },
  alert(options = {}) {
    return openBox(options, { variant: 'info', hasCancel: false, scrimCloses: true, refusal: undefined })
  },
  accept() {
    close(true)
  },
  cancel() {
    close(state.hasCancel ? false : undefined)
  },
}
