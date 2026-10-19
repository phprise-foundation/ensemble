<script setup>
import { onMounted, onUnmounted } from 'vue'
import ConsoleInput from './ConsoleInput.vue'
import ConsoleOutput from './ConsoleOutput.vue'
import { consoleStore } from '../store/console.js'
import {
  CONSOLE_HEIGHT_STEP,
  addConsoleHeight,
  consoleHeightValue,
  resetConsoleHeight,
  setConsoleHeight,
} from '../store/consoleHeight.js'

const emit = defineEmits(['close'])

function onKeydown(event) {
  const handlers = { ArrowUp: grow, ArrowDown: shrink }
  const handler = handlers[event.key]
  if (!handler) {
    return
  }
  event.preventDefault()
  handler()
}

function grow() {
  addConsoleHeight(CONSOLE_HEIGHT_STEP)
}

function shrink() {
  addConsoleHeight(-CONSOLE_HEIGHT_STEP)
}

function onPointerDown(event) {
  event.preventDefault()
  const startY = event.clientY
  const startHeight = consoleHeightValue()

  function onMove(moveEvent) {
    setConsoleHeight(startHeight + (startY - moveEvent.clientY))
  }

  function onUp() {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
}

onMounted(() => {
  consoleStore.start()
})

onUnmounted(() => {
  consoleStore.stop()
})
</script>

<template>
  <section id="app-console" class="console">
    <div
      class="console-resize"
      role="separator"
      tabindex="0"
      aria-orientation="horizontal"
      aria-label="Redimensionar console"
      @keydown="onKeydown"
      @pointerdown="onPointerDown"
      @dblclick="resetConsoleHeight"
    ></div>
    <div class="console-header">
      <span class="console-title">Console</span>
      <button class="icon-btn" type="button" title="Fechar console" aria-label="Fechar console" @click="emit('close')">
        ×
      </button>
    </div>
    <ConsoleOutput />
    <ConsoleInput />
  </section>
</template>
