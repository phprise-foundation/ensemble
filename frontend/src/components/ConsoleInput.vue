<script setup>
import { computed, ref } from 'vue'
import { consoleStore } from '../store/console.js'
import { runCommand } from '../store/commands.js'

const draft = ref('')
const history = []
const historyIndex = ref(-1)
const busy = computed(() => consoleStore.state.busy)

function submit() {
  const raw = draft.value
  if (busy.value || !raw.trim()) {
    return
  }
  history.push(raw)
  historyIndex.value = -1
  runCommand(raw)
  draft.value = ''
}

function onKeydown(event) {
  if (event.key === 'ArrowUp') {
    stepBack(event)
    return
  }
  if (event.key === 'ArrowDown') {
    stepForward(event)
  }
}

function stepBack(event) {
  if (history.length === 0) {
    return
  }
  event.preventDefault()
  const next = historyIndex.value < 0 ? history.length - 1 : Math.max(0, historyIndex.value - 1)
  historyIndex.value = next
  draft.value = history[next]
}

function stepForward(event) {
  if (historyIndex.value < 0) {
    return
  }
  event.preventDefault()
  const next = historyIndex.value + 1
  if (next >= history.length) {
    historyIndex.value = -1
    draft.value = ''
    return
  }
  historyIndex.value = next
  draft.value = history[next]
}
</script>

<template>
  <form class="console-input" aria-label="Console input" @submit.prevent="submit">
    <input
      v-model="draft"
      class="console-input-field"
      type="text"
      placeholder="Type a command… (/help)"
      :disabled="busy"
      @keydown="onKeydown"
    />
    <button class="console-input-send" type="submit" :disabled="busy">Enviar</button>
  </form>
</template>
