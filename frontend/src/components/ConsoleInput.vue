<script setup>
import { computed, ref } from 'vue'
import { consoleStore } from '../store/console.js'
import { runCommand } from '../store/commands.js'

const draft = ref('')
const history = []
const cursor = ref(-1)
const busy = computed(() => consoleStore.state.busy)

function submit() {
  const raw = draft.value
  if (busy.value || !raw.trim()) {
    return
  }
  history.push(raw)
  cursor.value = -1
  draft.value = ''
  runCommand(raw)
}

function previous() {
  if (history.length === 0) {
    return
  }
  cursor.value = cursor.value < 0 ? history.length - 1 : Math.max(0, cursor.value - 1)
  draft.value = history[cursor.value]
}

function next() {
  if (cursor.value < 0) {
    return
  }
  cursor.value += 1
  if (cursor.value >= history.length) {
    cursor.value = -1
    draft.value = ''
    return
  }
  draft.value = history[cursor.value]
}

function onKeydown(event) {
  const handlers = { ArrowUp: previous, ArrowDown: next }
  const handler = handlers[event.key]
  if (!handler) {
    return
  }
  event.preventDefault()
  handler()
}
</script>

<template>
  <form class="console-input" aria-label="Entrada do console" @submit.prevent="submit">
    <span class="console-prompt" aria-hidden="true">$</span>
    <input
      v-model="draft"
      class="console-input-field"
      type="text"
      placeholder="Digite um comando… (/help)"
      :disabled="busy"
      @keydown="onKeydown"
    />
    <button class="console-input-send" type="submit" :disabled="busy">Enviar</button>
  </form>
</template>
