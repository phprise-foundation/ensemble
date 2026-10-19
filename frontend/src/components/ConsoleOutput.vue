<script setup>
import { computed, ref, watch } from 'vue'
import { consoleStore } from '../store/console.js'

const PIN_TOLERANCE_PX = 24

const scroller = ref(null)
const pinned = ref(true)
const actor = ref('all')
const messages = consoleStore.state.messages

const actors = computed(() => [...new Set(messages.map((message) => message.actor))])

const visible = computed(() => {
  if (actor.value === 'all') {
    return messages
  }
  return messages.filter((message) => message.actor === actor.value)
})

function timeOf(message) {
  return message.at.slice(11, 19)
}

function scrollNow() {
  const element = scroller.value
  if (element) {
    element.scrollTop = element.scrollHeight
  }
}

function isAtBottom(element) {
  return element.scrollHeight - element.scrollTop - element.clientHeight <= PIN_TOLERANCE_PX
}

function onScroll() {
  const element = scroller.value
  if (!element) {
    return
  }
  pinned.value = isAtBottom(element)
  if (pinned.value) {
    consoleStore.clearUnread()
  }
}

function toBottom() {
  pinned.value = true
  consoleStore.clearUnread()
}

function keepAtBottom() {
  if (pinned.value) {
    scrollNow()
  }
}

watch(() => messages.length, keepAtBottom, { flush: 'post' })
watch(pinned, keepAtBottom, { flush: 'post' })
watch(actor, keepAtBottom, { flush: 'post' })
</script>

<template>
  <div class="console-panel">
    <select v-model="actor" class="console-filter" aria-label="Filtrar por agente">
      <option value="all">All</option>
      <option v-for="name in actors" :key="name" :value="name">{{ name }}</option>
    </select>
    <div ref="scroller" class="console-output" role="log" aria-live="polite" @scroll="onScroll">
      <p v-if="visible.length === 0" class="console-empty">Sem mensagens</p>
      <div
        v-for="message in visible"
        :key="message.seq"
        class="console-line"
        :class="message.kind"
      >
        <span class="console-time">{{ timeOf(message) }}</span>
        <span class="console-actor">{{ message.actor }}</span>
        <span class="console-text">{{ message.text }}</span>
      </div>
    </div>
    <button
      v-if="!pinned && consoleStore.state.unread > 0"
      class="console-jump"
      type="button"
      @click="toBottom"
    >
      {{ consoleStore.state.unread }} novas
    </button>
  </div>
</template>
