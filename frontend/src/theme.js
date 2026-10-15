import { reactive } from 'vue'

const KEY = 'multi-agent-theme'
const initial = localStorage.getItem(KEY) || 'dark'

export const theme = reactive({ mode: initial })

export function applyTheme() {
  document.documentElement.dataset.theme = theme.mode
}

export function toggleTheme() {
  theme.mode = theme.mode === 'dark' ? 'light' : 'dark'
  localStorage.setItem(KEY, theme.mode)
  applyTheme()
}

applyTheme()
