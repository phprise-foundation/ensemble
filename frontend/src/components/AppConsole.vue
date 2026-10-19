<script setup>
import { onMounted, onUnmounted } from 'vue'
import ConsoleInput from './ConsoleInput.vue'
import ConsoleOutput from './ConsoleOutput.vue'
import { consoleStore } from '../store/console.js'

const props = defineProps({
  height: { type: Number, required: true },
  step: { type: Number, required: true },
})

const emit = defineEmits(['close', 'resize', 'adjust', 'reset'])

function onKeydown(event) {
  const directions = { ArrowUp: 1, ArrowDown: -1 }
  const direction = directions[event.key]
  if (!direction) {
    return
  }
  event.preventDefault()
  emit('adjust', direction * props.step)
}

function onPointerDown(event) {
  event.preventDefault()
  const startY = event.clientY
  const startHeight = props.height

  function onMove(moveEvent) {
    emit('resize', startHeight + (startY - moveEvent.clientY))
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
      @dblclick="emit('reset')"
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
