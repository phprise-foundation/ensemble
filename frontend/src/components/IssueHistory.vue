<script setup>
import { computed } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  events: { type: Array, default: () => [] },
})

const ICON_BY_TYPE = {
  created: 'plus',
  status: 'transitions',
  assignee: 'agents',
  summary: 'edit',
  description: 'issues',
  comment: 'comment',
}
const FALLBACK_ICON = 'transitions'

const orderedEvents = computed(() =>
  props.events.slice().sort((a, b) => Date.parse(b.at) - Date.parse(a.at)),
)

function iconOf(event) {
  return ICON_BY_TYPE[event.type] ?? FALLBACK_ICON
}

function transitionLabel(from, to, resolve) {
  return `: ${resolve(from)} → ${resolve(to)}`
}

function resolveStatus(id) {
  return id ? boardStore.status(id)?.name ?? id : '—'
}

function resolveAgent(id) {
  if (!id) return 'Unassigned'
  return boardStore.agent(id)?.name ?? id
}

function resolveText(id) {
  return id ? String(id) : '—'
}

const TEXT_FOR = {
  created: (event) => event.summary ?? 'Issue created',
  status: (event) => `Status ${transitionLabel(event.from, event.to, resolveStatus)}`,
  assignee: (event) =>
    event.summary ? `Assignee ${event.summary}` : `Assignee ${transitionLabel(event.from, event.to, resolveAgent)}`,
  summary: (event) => event.summary ?? `Summary changed to “${event.to}”`,
  description: (event) => event.summary ?? 'Description updated',
  comment: (event) => (event.summary ? `Commented: ${event.summary}` : 'Comment added'),
  transition: (event) => `Status ${transitionLabel(event.from, event.to, resolveStatus)}`,
}

function textOf(event) {
  const known = event.type in TEXT_FOR
  const build = known ? TEXT_FOR[event.type] : null
  if (!known && (event.from !== undefined || event.to !== undefined)) {
    return TEXT_FOR.transition(event)
  }
  return build ? build(event) : event.summary ?? 'Issue updated'
}

function format(value) {
  return new Date(value).toLocaleString()
}
</script>

<template>
  <p v-if="!orderedEvents.length" class="muted small">No history yet</p>
  <ol v-else class="history-list">
    <li
      v-for="(event, index) in orderedEvents"
      :key="index"
      class="history-entry"
      :data-history-type="event.type || 'status'"
    >
      <span class="history-icon"><AppIcon :name="iconOf(event)" size="14" /></span>
      <div>
        <p class="history-text">
          <span class="history-actor">{{ event.actor || '—' }}</span> {{ textOf(event) }}
        </p>
        <p class="history-meta">
          <time :datetime="event.at">{{ format(event.at) }}</time>
        </p>
      </div>
    </li>
  </ol>
</template>
