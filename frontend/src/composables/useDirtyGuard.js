import { onBeforeUnmount, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { dialog } from '../store/dialog.js'

async function confirmDiscard() {
  return dialog.confirm({
    title: 'Discard changes?',
    message: 'Your unsaved changes will be lost.',
    danger: true,
  })
}

export function useDirtyGuard({ router, isDirty, confirmLeave = confirmDiscard }) {
  const allowNext = ref(false)
  const pendingTo = ref(null)

  async function gate(to) {
    if (!isDirty() || allowNext.value) {
      consume()
      return true
    }
    pendingTo.value = to.fullPath
    const leave = await confirmLeave()
    if (!leave) {
      consume()
      return false
    }
    allowNext.value = true
    const failure = await router.push(pendingTo.value)
    if (failure) consume()
    return false
  }

  function consume() {
    allowNext.value = false
    pendingTo.value = null
  }

  function onBeforeUnload(event) {
    if (!isDirty()) return
    event.preventDefault()
    event.returnValue = ''
  }

  window.addEventListener('beforeunload', onBeforeUnload)
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

  onBeforeRouteLeave((to) => gate(to))
  onBeforeRouteUpdate((to) => gate(to))
  onBeforeUnmount(consume)

  return { allowNext, pendingTo }
}
