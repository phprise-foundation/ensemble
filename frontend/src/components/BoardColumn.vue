<script setup>
import { computed } from 'vue'
import { boardStore } from '../store/board.js'
import IssueCard from './IssueCard.vue'

const props = defineProps({
  column: { type: Object, required: true },
  dragIssueId: { type: String, default: null },
  dragOver: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  dropBefore: { type: String, default: null },
  columnDragging: { type: Boolean, default: false },
  columnDropTarget: { type: Boolean, default: false },
})

const emit = defineEmits([
  'over',
  'drop',
  'drag-start',
  'drag-end',
  'card-over',
  'card-drop',
  'column-drag-start',
  'column-over',
  'column-drag-end',
  'column-drop',
])

const issues = computed(() => boardStore.issuesForColumn(props.column.id))
</script>

<template>
  <section
    class="column"
    :class="{ 'drag-over': dragOver, invalid, dragging: columnDragging }"
    @dragover.prevent="emit('over', column.id)"
    @drop.prevent="emit('drop', column.id)"
  >
    <header
      class="column-header"
      :class="{ 'drop-target': columnDropTarget }"
      draggable="true"
      title="Reorder column"
      @dragstart="emit('column-drag-start', column)"
      @dragover.prevent="emit('column-over', column)"
      @dragend="emit('column-drag-end')"
      @drop="emit('column-drop', column)"
    >
      <span class="column-title">{{ column.name }}</span>
      <span class="column-count">
        {{ issues.length }}<template v-if="column.wip"> / {{ column.wip }}</template>
      </span>
    </header>

    <div class="column-body">
      <IssueCard
        v-for="issue in issues"
        :key="issue.id"
        :issue="issue"
        :dragging="issue.id === dragIssueId"
        :drop-target="issue.id === dropBefore"
        @dragstart="emit('drag-start', issue)"
        @dragend="emit('drag-end')"
        @over="emit('card-over', issue.id)"
        @drop="emit('card-drop', issue.id)"
      />
      <p v-if="!issues.length" class="column-empty muted">Drop issues here</p>
    </div>
  </section>
</template>
