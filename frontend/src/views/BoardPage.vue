<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { boardStore } from '../store/board.js'
import BoardColumn from '../components/BoardColumn.vue'
import IssuePanel from '../components/IssuePanel.vue'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()

const dragIssueId = ref(null)
const dragOverColumn = ref(null)
const dropBefore = ref(null)
const editing = ref(false)
const draft = ref({})
const error = ref('')

const board = computed(() => boardStore.board.value)
const columns = computed(() => boardStore.boardColumns.value)
const statuses = computed(() => boardStore.boardStatuses.value)
const issueTypes = computed(() => boardStore.boardIssueTypes.value)
const dragIssue = computed(
  () => boardStore.state.issues.find((issue) => issue.id === dragIssueId.value) || null,
)

watch(
  () => [route.params.boardId, boardStore.state.boards.length],
  () => {
    const id = typeof route.params.boardId === 'string' ? route.params.boardId : null
    boardStore.setBoard(id)
  },
  { immediate: true },
)

function openCreate() {
  const initial = statuses.value.find((status) => status.type === 'initial')
  draft.value = {
    boardId: board.value?.id,
    summary: '',
    description: '',
    typeId: issueTypes.value[0]?.id || 'task',
    statusId: initial?.id || '',
    assigneeId: boardStore.state.agents[0]?.id || null,
  }
  error.value = ''
  editing.value = true
}

async function save() {
  error.value = ''
  if (!draft.value.summary) {
    error.value = 'Summary is required'
    return
  }
  draft.value.columnId = boardStore.columnOf(draft.value.statusId)
  await boardStore.createIssue(draft.value)
  editing.value = false
}

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
  const column = columns.value.find((candidate) => candidate.id === columnId)
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
</script>

<template>
  <div class="board-layout">
    <div class="board-main">
      <div class="board-toolbar">
        <strong>{{ board?.name || 'Board' }}</strong>
        <span class="badge sim" v-if="boardStore.simulated('board')"><span class="sim-dot sm"></span> Prototype</span>
        <span class="spacer"></span>
        <button class="button" @click="openCreate">
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

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>New task</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="editing = false"><AppIcon name="close" size="20" /></button>
        </header>
        <div class="modal-body">
          <label class="field"><span>Summary</span><input type="text" v-model="draft.summary" /></label>
          <label class="field"><span>Description</span><textarea rows="4" v-model="draft.description"></textarea></label>
          <label class="field">
            <span>Type</span>
            <select v-model="draft.typeId">
              <option v-for="type in issueTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
          <label class="field">
            <span>Status</span>
            <select v-model="draft.statusId">
              <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
            </select>
          </label>
          <label class="field">
            <span>Assignee</span>
            <select v-model="draft.assigneeId">
              <option :value="null">Unassigned</option>
              <option v-for="agent in boardStore.state.agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
            </select>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
        </div>
        <footer class="modal-footer">
          <button class="button ghost" @click="editing = false">Cancel</button>
          <button class="button" @click="save"><AppIcon name="check" size="16" /> Create</button>
        </footer>
      </div>
    </div>
  </div>
</template>
