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
  resolve: null
})

function applyOptions (options = {}) {
  state.title = options.title ?? ''
  state.message = options.message ?? ''
  state.confirmLabel = options.confirmLabel ?? 'Confirm'
  state.cancelLabel = options.cancelLabel ?? 'Cancel'
  state.variant = options.variant ?? 'info'
  state.role = options.danger === true ? 'alertdialog' : 'dialog'
  state.scrimCloses = options.scrimCloses ?? false
}

function finish (result) {
  const resolve = state.resolve
  state.resolve = null
  state.open = false
  resolve?.(result)
}

export const dialog = {
  /**
   * Open a confirm dialog. Resolves true (confirm) or false
   * (cancel / Escape / scrim). Resolves false immediately when
   * a dialog is already open.
   */
  confirm (options = {}) {
    if (state.open) return Promise.resolve(false)
    applyOptions(options)
    state.scrimCloses = options.scrimCloses ?? false
    return new Promise(resolve => {
      state.resolve = resolve
      state.open = true
    })
  },

  /**
   * Open an alert dialog. Single OK button, scrim closes by default.
   * Resolves immediately when a dialog is already open.
   */
  alert (options = {}) {
    return dialog.confirm({
      ...options,
      cancelLabel: '',
      confirmLabel: options.confirmLabel ?? 'OK',
      scrimCloses: options.scrimCloses ?? true
    }).then(() => undefined)
  }
}
