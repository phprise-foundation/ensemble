<script setup>
import AppIcon from './AppIcon.vue'
import { toastStore, toast } from '../store/toast.js'

const { items } = toastStore.state

function dismiss(id) {
  toast.remove(id)
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast">
        <div
          v-for="item in items"
          :key="item.id"
          class="toast"
          :class="`toast-${item.kind}`"
          :role="item.kind === 'error' ? 'alert' : 'status'"
        >
          <AppIcon
            class="toast-icon"
            :name="item.kind === 'success' ? 'check' : (item.kind === 'info' ? 'info' : 'warning')"
          />
          <span class="toast-text">{{ item.text }}</span>
          <button
            class="icon-btn toast-close"
            title="Close"
            aria-label="Close"
            @click="dismiss(item.id)"
          >
            <AppIcon name="close" size="14" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
