<script setup>
import { nextTick, ref, watch } from 'vue'
import { dialogStore, dialog } from '../store/dialog.js'
import AppIcon from './AppIcon.vue'

const state = dialogStore.state
const card = ref(null)
const cancelButton = ref(null)
const confirmButton = ref(null)
let lastFocused = null

function confirm() {
  dialog.confirmChoice()
}

function cancel() {
  dialog.refuse()
}

function onScrimClick() {
  if (state.scrimCloses) {
    dialog.refuse()
  }
}

function focusables() {
  const buttons = card.value ? Array.from(card.value.querySelectorAll('button')) : []
  return buttons.filter((button) => !button.disabled)
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    cancel()
    return
  }
  if (event.key === 'Enter') {
    if (event.target.nodeType === 1 && event.target.tagName === 'BUTTON') {
      return
    }
    event.preventDefault()
    confirm()
    return
  }
  if (event.key === 'Tab') {
    const elements = focusables()
    if (elements.length === 0) {
      return
    }
    const first = elements[0]
    const last = elements[elements.length - 1]
    const currentIndex = elements.indexOf(document.activeElement)
    if (event.shiftKey) {
      if (currentIndex <= 0) {
        event.preventDefault()
        last.focus()
      }
      return
    }
    if (currentIndex === elements.length - 1 || currentIndex === -1) {
      event.preventDefault()
      first.focus()
    }
  }
}

function applyInert(value) {
  const app = document.getElementById('app')
  if (app) {
    if (value) {
      app.setAttribute('inert', '')
    } else {
      app.removeAttribute('inert')
    }
  }
}

function variantIcon(variant) {
  if (variant === 'success') {
    return 'check'
  }
  if (variant === 'info') {
    return 'info'
  }
  if (variant === 'warning' || variant === 'danger') {
    return 'warning'
  }
  return 'close'
}

watch(
  () => state.open,
  (open) => {
    if (open) {
      lastFocused = document.activeElement
      applyInert(true)
      document.body.style.overflow = 'hidden'
      document.body.classList.add('dialog-locked')
      nextTick(() => {
        const target = state.cancelLabel ? cancelButton.value : confirmButton.value
        target?.focus()
      })
    } else {
      applyInert(false)
      document.body.style.overflow = ''
      document.body.classList.remove('dialog-locked')
      nextTick(() => {
        if (lastFocused && document.contains(lastFocused)) {
          lastFocused.focus()
        }
        lastFocused = null
      })
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="state.open"
      class="dialog-overlay"
      @click.self="onScrimClick"
      @keydown="onKeydown"
    >
      <div
        ref="card"
        class="dialog-card"
        :role="state.role"
        aria-modal="true"
        aria-labelledby="dialog-title"
        aria-describedby="dialog-message"
        tabindex="-1"
      >
        <h3 id="dialog-title" :class="['dialog-title', `dialog-variant-${state.variant}`]">
          <AppIcon class="dialog-title-icon" :name="variantIcon(state.variant)" />
          {{ state.title }}
        </h3>
        <p id="dialog-message" v-if="state.message" class="dialog-message">{{ state.message }}</p>
        <div class="dialog-actions">
          <button
            v-if="state.cancelLabel"
            ref="cancelButton"
            class="button ghost"
            @click="cancel()"
          >
            {{ state.cancelLabel }}
          </button>
          <button
            ref="confirmButton"
            class="button"
            :class="{ danger: state.variant === 'danger' }"
            @click="confirm()"
          >
            {{ state.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>