<script setup>
import { computed, ref } from 'vue'
import { boardStore } from '../store/board.js'
import BoardColumn from '../components/BoardColumn.vue'
import IssuePanel from '../components/IssuePanel.vue'
import AppIcon from '../components/AppIcon.vue'

const dragIssueId = ref(null)
const dragOverColumn = ref(null)
const dropBefore = ref(null)
const composing = ref(false)
const newSummary = ref('')

const columns = computed(() => boardStore.state.columns)
const dragIssue = computed(
  () => boardStore.state.issues.find((issue) => issue.id === dragIssueId.value) || null,
)

function startDrag(issue) {
  dragIssueId.value = issue.id
}

function endDrag() {
  dragIssueId.value = null
  dragOverColumn.value = null
  dropBefore.value = null
}

function onColumnOver(columnId) {
  if (!dragIssueId.value) {
    return
  }
  dragOverColumn.value = columnId
  dropBefore.value = null
}

function onCardOver(columnId, issueId) {
  if (!dragIssueId.value) {
    return
  }
  dragOverColumn.value = columnId
  dropBefore.value = issueId
}

function invalid(columnId) {
  return Boolean(dragIssue.value) && dragOverColumn.value === columnId && !boardStore.canEnterColumn(dragIssue.value.statusId, columnId)
}

async function drop(columnId, beforeId) {
  const id = dragIssueId.value
  if (!id) {
    return
  }
  const issue = boardStore.state.issues.find((candidate) => candidate.id === id)
  if (!issue || !boardStore.canEnterColumn(issue.statusId, columnId)) {
    return endDrag()
  }
  const column = boardStore.column(columnId)
  let statusId = issue.statusId
  if (!column.statusIds.includes(statusId)) {
    statusId = column.statusIds.find((candidate) => boardStore.canUserMove(issue.statusId, candidate))
  }
  const list = boardStore.issuesForColumn(columnId).filter((candidate) => candidate.id !== id)
  let index = list.length
  if (beforeId) {
    const position = list.findIndex((candidate) => candidate.id === beforeId)
    if (position >= 0) {
      index = position
    }
  }
  await boardStore.moveIssue(id, { columnId, statusId, order: index })
  endDrag()
}

async function create() {
  if (!newSummary.value.trim()) {
    return
  }
  const initial = boardStore.state.statuses.find((status) => status.type === 'initial')
  await boardStore.createIssue({
    summary: newSummary.value.trim(),
    statusId: initial.id,
    columnId: boardStore.columnOf(initial.id),
  })
  newSummary.value = ''
  composing.value = false
}
</script>

<template>
  <div class="board-layout">
    <div class="board-main">
      <div class="board-toolbar">
        <span class="badge sim" v-if="boardStore.simulated('board')"><span class="sim-dot sm"></span> Simulated</span>
        <span class="spacer"></span>
        <form v-if="composing" class="composer" @submit.prevent="create">
          <input v-model="newSummary" placeholder="Task summary" autofocus />
          <button class="button" type="submit">Add</button>
          <button class="button ghost" type="button" @click="composing = false">Cancel</button>
        </form>
        <button v-else class="button" @click="composing = true">
          <AppIcon name="plus" size="16" /> New task
        </button>
      </div>

      <p v-if="boardStore.state.loading" class="muted pad">Loading board…</p>
      <p v-else-if="boardStore.state.error" class="error pad">{{ boardStore.state.error }}</p>

      <div v-else class="columns">
        <BoardColumn
          v-for="column in columns"
          :key="column.id"
          :column="column"
          :drag-issue-id="dragIssueId"
          :drag-over="dragOverColumn === column.id"
          :invalid="invalid(column.id)"
          :drop-before="dragOverColumn === column.id ? dropBefore : null"
          @over="onColumnOver"
          @drop="drop(column.id, null)"
          @drag-start="startDrag"
          @drag-end="endDrag"
          @card-over="(issueId) => onCardOver(column.id, issueId)"
          @card-drop="(issueId) => drop(column.id, issueId)"
        />
      </div>
    </div>

    <IssuePanel />
  </div>
</template>
