<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'
import Avatar from './Avatar.vue'

defineProps({
  assignee: { type: Object, default: null },
  agents: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])

const open = ref(false)
const root = ref(null)

function choose(id) {
  open.value = false
  emit('select', id)
}

function onDocumentClick(event) {
  if (open.value && root.value && !root.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <div ref="root" class="assignee-picker">
    <button
      type="button"
      class="assignee-trigger"
      :aria-expanded="open"
      aria-haspopup="listbox"
      aria-label="Change assignee"
      @click="open = !open"
      @keydown.esc="open = false"
    >
      <Avatar
        size="md"
        :name="assignee?.name ?? '?'"
        :image="assignee?.portrait ?? null"
        :color="assignee?.avatarColor || 'var(--surface-3)'"
        decorative
      />
      <span class="assignee-name">{{ assignee?.name ?? 'Unassigned' }}</span>
      <AppIcon name="chevron" size="14" class="assignee-chevron" />
    </button>

    <ul v-if="open" class="assignee-menu" role="listbox" aria-label="Assignee">
      <li>
        <button
          type="button"
          role="option"
          :aria-selected="!assignee"
          @click="choose(null)"
        >
          <span class="assignee-ghost">?</span>
          <span>Unassigned</span>
        </button>
      </li>
      <li v-for="agent in agents" :key="agent.id">
        <button
          type="button"
          role="option"
          :aria-selected="assignee?.id === agent.id"
          @click="choose(agent.id)"
        >
          <Avatar
            size="sm"
            :name="agent.name"
            :image="agent.portrait ?? null"
            :color="agent.avatarColor"
            decorative
          />
          <span>{{ agent.name }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
