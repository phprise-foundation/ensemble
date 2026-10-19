<script setup>
import { computed, ref } from 'vue'
import { consoleStore } from '../store/console.js'
import { runCommand } from '../store/commands.js'

const draft = ref('')
const history = []
const cursor = ref(-1)
const pending = ref('')
const busy = computed(() => consoleStore.state.busy)

function candidate() {
  return draft.value.trim()
}

function submit() {
  const raw = candidate()
  if (busy.value || !raw) {
    return
  }
  commit(raw)
}

function commit(raw) {
  history.push(raw)
  cursor.value = -1
  pending.value = ''
  draft.value = ''
  runCommand(raw)
}

function previous() {
  if (history.length === 0) {
    return
  }
  const leaving = cursor.value < 0
  pending.value = leaving ? draft.value : pending.value
  cursor.value = leaving ? history.length - 1 : Math.max(0, cursor.value - 1)
  draft.value = history[cursor.value]
}

function next() {
  if (cursor.value < 0) {
    return
  }
  cursor.value += 1
  draft.value = cursor.value >= history.length ? pending.value : history[cursor.value]
  cursor.value = cursor.value >= history.length ? -1 : cursor.value
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
