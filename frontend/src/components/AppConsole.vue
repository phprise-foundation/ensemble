<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import ConsoleInput from './ConsoleInput.vue'
import ConsoleOutput from './ConsoleOutput.vue'
import { consoleStore } from '../store/console.js'

const emit = defineEmits(['close'])

const DEFAULT_HEIGHT = 240
const MIN_HEIGHT = 120
const MAX_VIEWPORT_RATIO = 0.6
const STEP = 16

const height = ref(DEFAULT_HEIGHT)

const style = computed(() => ({ '--console-height': `${height.value}px` }))

function maxHeight() {
  if (typeof window === 'undefined') {
    return DEFAULT_HEIGHT
  }
  return Math.round(window.innerHeight * MAX_VIEWPORT_RATIO)
}

function clamp(value) {
  return Math.min(Math.max(value, MIN_HEIGHT), Math.max(MIN_HEIGHT, maxHeight()))
}

function setHeight(value) {
  height.value = clamp(value)
}

function grow() {
  setHeight(height.value + STEP)
}

function shrink() {
  setHeight(height.value - STEP)
}

function onKeydown(event) {
  const handlers = { ArrowUp: grow, ArrowDown: shrink }
  const handler = handlers[event.key]
  if (!handler) {
    return
  }
  event.preventDefault()
  handler()
}

function onPointerDown(event) {
  event.preventDefault()
  const startY = event.clientY
  const startHeight = height.value

  function onMove(moveEvent) {
    setHeight(startHeight + (startY - moveEvent.clientY))
  }

  function onUp() {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
}

function reset() {
  setHeight(DEFAULT_HEIGHT)
}

onMounted(() => {
  consoleStore.start()
})

onUnmounted(() => {
  consoleStore.stop()
})
</script>

<template>
  <section id="app-console" class="console" :style="style">
    <div
      class="console-resize"
      role="separator"
      tabindex="0"
      aria-orientation="horizontal"
      aria-label="Resize console"
      @keydown="onKeydown"
      @pointerdown="onPointerDown"
      @dblclick="reset"
    ></div>
    <div class="console-header">
      <span class="console-title">Console</span>
      <button class="icon-btn" type="button" title="Close console" aria-label="Close console" @click="emit('close')">
        ×
      </button>
    </div>
    <ConsoleOutput />
    <ConsoleInput />
  </section>
</template>
