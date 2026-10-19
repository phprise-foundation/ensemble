import { computed, ref } from 'vue'

export const DEFAULT_CONSOLE_HEIGHT = 240
export const MIN_CONSOLE_HEIGHT = 120
export const MAX_CONSOLE_VIEWPORT_RATIO = 0.6
export const CONSOLE_HEIGHT_STEP = 16

const height = ref(DEFAULT_CONSOLE_HEIGHT)

export function maximumHeight() {
  if (typeof window === 'undefined') {
    return DEFAULT_CONSOLE_HEIGHT
  }
  return Math.round(window.innerHeight * MAX_CONSOLE_VIEWPORT_RATIO)
}

export function clampHeight(value) {
  return Math.min(Math.max(value, MIN_CONSOLE_HEIGHT), Math.max(MIN_CONSOLE_HEIGHT, maximumHeight()))
}

export const consoleHeight = computed(() => `${height.value}px`)

export function addConsoleHeight(delta) {
  height.value = clampHeight(height.value + delta)
}

export function setConsoleHeight(value) {
  height.value = clampHeight(value)
}

export function resetConsoleHeight() {
  height.value = DEFAULT_CONSOLE_HEIGHT
}

export function consoleHeightValue() {
  return height.value
}

export const consoleHeightStyle = computed(() => ({ '--console-height': consoleHeight.value }))
