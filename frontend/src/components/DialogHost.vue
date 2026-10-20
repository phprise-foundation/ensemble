<script setup>
import { computed } from 'vue'
import { dialogStore, dialog } from '../store/dialog.js'
import AppIcon from './AppIcon.vue'

const state = dialogStore.state

const variantIcon = computed(() => {
  if (state.variant === 'success') return 'check'
  if (state.variant === 'warning' || state.variant === 'danger') return 'warning'
  return 'info'
})

const role = computed(() => (state.variant === 'danger' ? 'alertdialog' : 'dialog'))

function finish(result) {
  state.resolve = null
  state.open = false
  resolve(value)
}
