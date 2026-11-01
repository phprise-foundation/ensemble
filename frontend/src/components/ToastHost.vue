<script setup>
import { computed, ref } from 'vue'
import { toast } from '../store/toast.js'
import AppIcon from './AppIcon.vue'

const VARIANT_ICONS = {
  info: 'info',
  success: 'check',
  warning: 'warning',
  danger: 'warning',
  error: 'warning',
}

const items = computed(() => toast.toasts)
const copiedId = ref(null)

const variantIcon = (kind) => VARIANT_ICONS[kind] || 'info'
const titleOf = (kind) => (kind === 'error' ? 'Error' : kind === 'warning' ? 'Warning' : '')

function handleClose(id) {
  toast.dismiss(id)
}

async function copy(item) {
  try {
    await navigator.clipboard.writeText(item.text)
    copiedId.value = item.id
    window.setTimeout(() => {
      if (copiedId.value === item.id) {
        copiedId.value = null
      }
    }, 1500)
  } catch {
    copiedId.value = null
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite">
      <div
        v-for="(item, index) in items"
        :key="item.id"
        class="toast"
        :class="item.kind"
        :role="item.kind === 'error' ? 'alert' : undefined"
        :data-index="index"
      >
        <AppIcon
          :name="variantIcon(item.kind)"
          :size="16"
          class="toast-icon"
        />
        <div class="toast-main">
          <strong v-if="titleOf(item.kind)" class="toast-title">{{ titleOf(item.kind) }}</strong>
          <span class="toast-text">{{ item.text }}</span>
          <span v-if="item.kind === 'error'" class="toast-hint">Saved to the console.</span>
        </div>
        <div class="toast-actions">
          <button
            v-if="item.kind === 'error'"
            type="button"
            class="toast-action"
            @click="copy(item)"
          >
            {{ copiedId === item.id ? 'Copied' : 'Copy' }}
          </button>
          <button
            type="button"
            class="icon-btn toast-close"
            aria-label="Close"
            @click="handleClose(item.id)"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
