<script setup>
import { nextTick, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import { dialog } from '../store/dialog.js'

const state = dialog.state
const card = ref(null)
const cancelButton = ref(null)
const confirmButton = ref(null)
let previousFocus = null

const ROLE = { dialog: 'dialog', alertdialog: 'alertdialog' }

function role() {
  return state.danger ? ROLE.alertdialog : ROLE.dialog
}

function variantIcon() {
  return { info: 'info', success: 'check', warning: 'warning', danger: 'warning' }[state.variant] ?? 'info'
}

function applySideEffects(open) {
  const app = document.getElementById('app')
  if (app) app.inert = open
  document.body.style.overflow = open ? 'hidden' : ''
}

function restoreFocus() {
  nextTick(() => {
    if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus()
    previousFocus = null
  })
}

watch(
  () => state.open,
  (open) => {
    applySideEffects(open)
    if (!open) {
      restoreFocus()
      return
    }
    previousFocus = document.activeElement
    nextTick(() => {
      const target = state.hasCancel ? cancelButton.value : confirmButton.value
      if (target) target.focus()
    })
  },
)

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    dialog.cancel()
    return
  }
  if (event.key === 'Enter' && !(event.target instanceof HTMLButtonElement)) {
    event.preventDefault()
    dialog.accept()
    return
  }
  if (event.key === 'Tab') {
    cycleFocus(event)
  }
}

function cycleFocus(event) {
  const focusable = [...card.value.querySelectorAll('button')]
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="state.open" class="dialog-overlay" @click.self="state.scrimCloses && dialog.cancel()">
      <div
        ref="card"
        class="dialog-card"
        :class="`dialog-${state.variant}`"
        :role="role()"
        aria-modal="true"
        aria-labelledby="dialog-title"
        :aria-describedby="state.message ? 'dialog-message' : undefined"
        @keydown="onKeydown"
      >
        <div class="dialog-heading">
          <AppIcon :name="variantIcon()" size="18" class="dialog-icon" />
          <h3 id="dialog-title" class="dialog-title">{{ state.title }}</h3>
        </div>
        <p v-if="state.message" id="dialog-message" class="dialog-message">{{ state.message }}</p>
        <div class="dialog-actions">
          <button v-if="state.hasCancel" ref="cancelButton" class="button ghost" @click="dialog.cancel()">
            {{ state.cancelLabel }}
          </button>
          <button ref="confirmButton" class="button" :class="{ danger: state.danger }" @click="dialog.accept()">
            {{ state.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
