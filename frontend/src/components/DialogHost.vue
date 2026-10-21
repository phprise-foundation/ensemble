<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { dialog } from '../store/dialog.js'
import AppIcon from './AppIcon.vue'

const dialogCard = ref(null)

const VARIANT_ICONS = {
  info: 'info',
  success: 'check',
  warning: 'warning',
  danger: 'warning',
}

const variantIcon = computed(() => VARIANT_ICONS[dialog.variant] || 'info')

let lastFocusedElement = null

function isButtonTarget(target) {
  return target instanceof Element && target.closest('button') !== null
}

function applyLocks() {
  document.body.style.overflow = 'hidden'
  const appElement = document.getElementById('app')
  if (appElement) {
    appElement.inert = true
  }
}

function releaseLocks() {
  document.body.style.overflow = ''
  const appElement = document.getElementById('app')
  if (appElement) {
    appElement.inert = false
  }
}

function focusInitialElement() {
  if (!dialogCard.value) return

  if (dialog.cancelLabel) {
    const cancelButton = dialogCard.value.querySelector('.dialog-cancel')
    if (cancelButton) {
      cancelButton.focus()
      return
    }
  }
  const confirmButton = dialogCard.value.querySelector('.dialog-confirm')
  if (confirmButton) {
    confirmButton.focus()
  }
}

function restoreFocus() {
  if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
    lastFocusedElement.focus()
  }
  lastFocusedElement = null
}

function handleKeydown(event) {
  if (!dialog.open) return

  if (event.key === 'Escape') {
    event.preventDefault()
    dialog.finish(false)
    return
  }

  if (event.key === 'Enter' && !isButtonTarget(event.target)) {
    event.preventDefault()
    dialog.finish(true)
  }
}

function handleFocusTrap(event) {
  if (!dialogCard.value) return

  const focusable = Array.from(
    dialogCard.value.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
  )
  if (focusable.length === 0) return

  const firstElement = focusable[0]
  const lastElement = focusable[focusable.length - 1]

  if (event.shiftKey && event.target === firstElement) {
    event.preventDefault()
    lastElement.focus()
  } else if (!event.shiftKey && event.target === lastElement) {
    event.preventDefault()
    firstElement.focus()
  }
}

function handleScrimClick() {
  if (dialog.scrimCloses) {
    dialog.finish(false)
  }
}

watch(
  () => dialog.open,
  (newOpen) => {
    if (newOpen) {
      lastFocusedElement = document.activeElement
      nextTick(() => {
        focusInitialElement()
        applyLocks()
      })
    } else {
      nextTick(() => {
        releaseLocks()
        restoreFocus()
      })
    }
  }
)

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  if (dialog.open) {
    lastFocusedElement = document.activeElement
    nextTick(() => {
      focusInitialElement()
      applyLocks()
    })
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
  if (dialog.open) {
    releaseLocks()
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="dialog.open"
      class="dialog-overlay"
      @click.self="handleScrimClick"
    >
      <div
        ref="dialogCard"
        class="dialog-card"
        :role="dialog.variant === 'danger' ? 'alertdialog' : 'dialog'"
        :aria-modal="true"
        aria-labelledby="dialog-title"
        :aria-describedby="dialog.message ? 'dialog-message' : undefined"
        tabindex="-1"
      >
        <div class="dialog-header">
          <span class="dialog-icon" :class="'variant-' + dialog.variant">
            <AppIcon :name="variantIcon" :size="20" />
          </span>
          <h2 id="dialog-title" class="dialog-title">
            {{ dialog.title }}
          </h2>
        </div>
        <div
          v-if="dialog.message"
          id="dialog-message"
          class="dialog-message"
        >
          {{ dialog.message }}
        </div>
        <div class="dialog-actions">
          <button
            v-if="dialog.cancelLabel"
            type="button"
            class="button ghost dialog-cancel"
            @click="dialog.finish(false)"
          >
            {{ dialog.cancelLabel }}
          </button>
          <button
            type="button"
            class="button dialog-confirm"
            :class="{ danger: dialog.variant === 'danger' }"
            @click="dialog.finish(true)"
          >
            {{ dialog.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
