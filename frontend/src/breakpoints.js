import { onMounted, onUnmounted, ref } from 'vue'

const SIDEBAR_BREAKPOINT_PROPERTY = '--breakpoint-sidebar'

export function sidebarBreakpoint() {
  return getComputedStyle(document.documentElement).getPropertyValue(SIDEBAR_BREAKPOINT_PROPERTY).trim()
}

export function mobileSidebarQuery() {
  return `(max-width: ${sidebarBreakpoint()})`
}

export function useMobileSidebar() {
  const mobile = ref(false)
  let query = null

  function sync(event) {
    mobile.value = event.matches
  }

  onMounted(() => {
    query = window.matchMedia(mobileSidebarQuery())
    mobile.value = query.matches
    query.addEventListener('change', sync)
  })

  onUnmounted(() => {
    query?.removeEventListener('change', sync)
  })

  return mobile
}
