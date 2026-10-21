<script setup>
import { computed } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'
import Avatar from './Avatar.vue'

const props = defineProps({
  issue: { type: Object, required: true },
  dragging: { type: Boolean, default: false },
  dropTarget: { type: Boolean, default: false },
})

const emit = defineEmits(['dragstart', 'dragend', 'over', 'drop'])

const status = computed(() => boardStore.status(props.issue.statusId))
const agent = computed(() => boardStore.agent(props.issue.assigneeId))
const overdue = computed(() => boardStore.isOverdue(props.issue))

function onDragStart(event) {
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', props.issue.id)
  emit('dragstart')
}
</script>

<template>
  <article
    class="card"
    :class="{ dragging, 'drop-target': dropTarget, overdue }"
    :style="{ '--status-color': status?.color }"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="emit('dragend')"
    @dragover.prevent.stop="emit('over')"
    @drop.prevent.stop="emit('drop')"
    @click="boardStore.select(issue.id)"
  >
    <div class="card-top">
      <Avatar
        v-if="agent"
        class="avatar sm"
        :name="agent.name"
        :image="agent.avatar"
        :color="agent.avatarColor"
        size="sm"
      />
      <span v-else class="avatar sm" :style="{ background: 'var(--surface-3)' }">?</span>
      <span class="card-id muted">{{ issue.id }}</span>
      <span class="spacer"></span>
      <AppIcon v-if="overdue" name="warning" size="14" class="overdue-flag" />
    </div>
    <p class="card-summary">{{ issue.summary }}</p>
    <div class="card-flags">
      <span class="flag" :style="{ background: status?.color }">{{ status?.name }}</span>
      <span v-if="overdue" class="flag overdue">aging</span>
    </div>
  </article>
</template>
