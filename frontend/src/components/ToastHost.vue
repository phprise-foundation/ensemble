<script setup>
import AppIcon from './AppIcon.vue'
import { toast } from '../store/toast.js'

const items = toast.items

const ICONS = { success: 'check', error: 'warning', warning: 'warning', info: 'info' }
</script>

<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite">
      <div
        v-for="item in items"
        :key="item.id"
        class="toast"
        :class="`toast-${item.kind}`"
        :role="item.kind === 'error' ? 'alert' : undefined"
      >
        <AppIcon :name="ICONS[item.kind] ?? 'info'" size="16" class="toast-icon" />
        <span class="toast-text">{{ item.text }}</span>
        <button class="icon-btn toast-close" aria-label="Close" @click="toast.dismiss(item.id)">
          <AppIcon name="close" size="14" />
        </button>
      </div>
    </div>
  </Teleport>
</template>
