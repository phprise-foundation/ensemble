<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { consoleStore } from '../store/console.js'

const PIN_TOLERANCE_PX = 24
const ACTIVITY_MS = 1500

const scroller = ref(null)
const pinned = ref(true)
const actor = ref('all')
const active = ref(false)
const messages = computed(() => consoleStore.state.messages)
const unread = computed(() => consoleStore.state.unread)

const actors = computed(() => [...new Set(messages.value.map((message) => message.actor))])

const visible = computed(() => {
  if (actor.value === 'all') {
    return messages.value
  }
  return messages.value.filter((message) => message.actor === actor.value)
})

function timeOf(message) {
  return new Date(message.at).toLocaleTimeString()
}

function isAtBottom(element) {
  return element.scrollHeight - element.scrollTop - element.clientHeight <= PIN_TOLERANCE_PX
}

function anchorHere() {
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
    scroller.value.scrollTop = scroller.value.scrollHeight
  }
}

let activityTimer = null

function signalActivity() {
  active.value = true
  clearTimeout(activityTimer)
  activityTimer = setTimeout(() => {
    active.value = false
  }, ACTIVITY_MS)
}

function keepAtBottom() {
  const element = scroller.value
  if (pinned.value && element) {
    element.scrollTop = element.scrollHeight
  }
}

onMounted(() => {
  nextTick(() => {
    toBottom()
    anchorHere()
  })
})

onUnmounted(() => {
  clearTimeout(activityTimer)
})

watch(() => messages.value.length, () => {
  keepAtBottom()
  anchorHere()
  signalActivity()
}, { flush: 'post' })

watch(actor, keepAtBottom, { flush: 'post' })
</script>

<template>
  <div class="console-panel">
    <div class="console-toolbar">
      <span class="console-activity" :class="{ active }" :aria-label="active ? 'Nova saída' : 'Ocioso'"></span>
      <select v-model="actor" class="console-filter" aria-label="Filtrar por ator">
        <option value="all">Todas</option>
        <option v-for="name in actors" :key="name" :value="name">{{ name }}</option>
      </select>
    </div>
    <div ref="scroller" class="console-output" role="log" aria-live="polite" @scroll="anchorHere">
      <p v-if="visible.length === 0" class="console-empty">Nenhuma mensagem</p>
      <div v-for="message in visible" :key="message.seq" class="console-line" :class="message.kind">
        <span class="console-time">{{ timeOf(message) }}</span>
        <span class="console-actor">{{ message.actor }}</span>
        <span class="console-text">{{ message.text }}</span>
      </div>
    </div>
    <button
      v-if="!pinned && unread > 0"
      class="console-jump"
      type="button"
      @click="toBottom"
    >
      {{ unread }} nova{{ unread === 1 ? '' : 's' }}
    </button>
  </div>
</template>
