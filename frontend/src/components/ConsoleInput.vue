<script setup>
import { computed, ref } from 'vue'
import { consoleStore } from '../store/console.js'
import { runCommand } from '../store/commands.js'

const draft = ref('')
const history = []
const historyIndex = ref(-1)
const pending = ref('')
const busy = computed(() => consoleStore.state.busy)

function send(raw) {
  history.push(raw)
  historyIndex.value = -1
  pending.value = ''
  draft.value = ''
  runCommand(raw)
}

function submit() {
  const raw = draft.value.trim()
  if (busy.value || !raw) {
    return
  }
  send(raw)
}

function onKeydown(event) {
  const handlers = { ArrowUp: stepBack, ArrowDown: stepForward }
  const handler = handlers[event.key]
  if (!handler) {
    return
  }
  event.preventDefault()
  handler()
}

function stepBack() {
  if (history.length === 0) {
    return
  }
  const leaving = historyIndex.value < 0
  const next = leaving ? history.length - 1 : Math.max(0, historyIndex.value - 1)
  pending.value = leaving ? draft.value : pending.value
  historyIndex.value = next
  draft.value = history[next]
}

function stepForward() {
  if (historyIndex.value < 0) {
    return
  }
  const next = historyIndex.value + 1
  if (next < history.length) {
    historyIndex.value = next
    draft.value = history[next]
    return
  }
  historyIndex.value = -1
  draft.value = pending.value
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
    <button class="console-input-send" type="submit" :disabled="busy">Send</button>
  </form>
</template>
