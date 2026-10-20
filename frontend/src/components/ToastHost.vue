<script setup>
import { computed, ref, watch } from 'vue'
import { toast } from '../store/toast.js'
import AppIcon from './AppIcon.vue'

const toastRefs = ref([])

const VARIANT_ICONS = {
  info: 'info',
  success: 'check',
  warning: 'warning',
  danger: 'warning',
}

const items = computed(() => toast.toasts)

const variantIcon = (kind) => VARIANT_ICONS[kind] || 'info'

function handleClose(id) {
  toast.removeToast(id)
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite">
      <div
        v-for="(item, index) in items"
        :key="item.id"
        ref="toastRefs"
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
        <span class="toast-text">{{ item.text }}</span>
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
  </Teleport>
</template>
