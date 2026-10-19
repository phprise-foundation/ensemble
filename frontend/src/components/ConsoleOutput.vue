<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { consoleStore } from '../store/console.js'

const PIN_TOLERANCE_PX = 24
const ACTIVITY_MS = 1500

const scroller = ref(null)
const pinned = ref(true)
const actor = ref('all')
const active = ref(false)
const messages = computed(() => consoleStore.state.messages)

const actors = computed(() => [...new Set(messages.value.map((message) => message.actor))])

const visible = computed(() => {
  if (actor.value === 'all') {
    return messages.value
  }
  return messages.value.filter((message) => message.actor === actor.value)
})

function timeOf(message) {
  const date = new Date(message.at)
  if (Number.isNaN(date.getTime())) {
    return message.at
  }
  return date.toLocaleTimeString('pt-BR', { hour12: false })
}

function scrollToEnd(element) {
  element.scrollTop = element.scrollHeight
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
  if (scroller.value) {
    scrollToEnd(scroller.value)
  }
}

let activityTimer = null

function signalActivity() {
  active.value = true
  clearTimeout(activityTimer)
  activityTimer = setTimeout(() => {
    active.value = false
    activityTimer = null
  }, ACTIVITY_MS)
}

onUnmounted(() => {
  clearTimeout(activityTimer)
  activityTimer = null
})

function keepAtBottom() {
  if (pinned.value && scroller.value) {
    scrollToEnd(scroller.value)
  }
}

watch([() => messages.value.length, () => consoleStore.state.unread], () => {
  const latest = messages.value.at(-1)
  if (latest?.kind === 'agent' && consoleStore.state.unread > 0) {
    signalActivity()
  }
  keepAtBottom()
}, { flush: 'post' })
watch([pinned, actor], keepAtBottom, { flush: 'post' })
</script>

<template>
  <div class="console-panel">
    <div class="console-toolbar">
      <span class="console-activity" :class="{ active }" :aria-label="active ? 'New output' : 'Idle'"></span>
      <select v-model="actor" class="console-filter" aria-label="Filter by actor">
        <option value="all">All</option>
        <option v-for="name in actors" :key="name" :value="name">{{ name }}</option>
      </select>
    </div>
    <div ref="scroller" class="console-output" role="log" aria-live="polite" @scroll="onScroll">
      <p v-if="visible.length === 0" class="console-empty">No messages</p>
      <div v-for="message in visible" :key="message.seq" class="console-line" :class="message.kind">
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
      {{ consoleStore.state.unread }} new
    </button>
  </div>
</template>
