<script setup>
import { computed, nextTick, ref, watch } from 'vue'
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
const summaryInput = ref(null)
const summaryError = ref('')
const statusError = ref('')

const board = computed(() => boardStore.board.value)
const columns = computed(() => boardStore.boardColumns.value)
const statuses = computed(() => boardStore.boardStatuses.value)
const issueTypes = computed(() => boardStore.boardIssueTypes.value)
const initialStatuses = computed(() => statuses.value.filter((status) => status.type === 'initial'))
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

async function openCreate() {
  draft.value = {
    boardId: board.value?.id,
    summary: '',
    description: '',
    typeId: issueTypes.value[0]?.id || 'task',
    statusId: initialStatuses.value[0]?.id || '',
    assigneeId: boardStore.state.agents[0]?.id || null,
  }
  error.value = ''
  summaryError.value = ''
  statusError.value = ''
  editing.value = true
  await nextTick()
  summaryInput.value?.focus()
}

watch(() => draft.value.summary, () => { summaryError.value = '' })
watch(() => draft.value.statusId, () => { statusError.value = '' })
watch(initialStatuses, (initials) => {
  if (!editing.value || initials.some((status) => status.id === draft.value.statusId)) return
  draft.value = { ...draft.value, statusId: initials[0]?.id || '' }
})

function applyFailure(failure) {
  if (failure.field === 'statusId') {
    statusError.value = failure.message
    return
  }
  error.value = failure.message || 'Não foi possível criar a task.'
}

async function save() {
  error.value = ''
  summaryError.value = ''
  statusError.value = ''
  if (!draft.value.summary) {
    summaryError.value = 'O resumo é obrigatório.'
    summaryInput.value?.focus()
    return
  }
  if (!initialStatuses.value.length) return
  if (!draft.value.statusId) {
    statusError.value = 'Selecione um status inicial.'
    return
  }
  try {
    await boardStore.createIssue(draft.value)
  } catch (failure) {
    applyFailure(failure)
    return
  }
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
      <div class="modal-card new-task-modal">
        <header class="modal-header">
          <h3>New task</h3>
          <button class="icon-btn" @click="editing = false"><AppIcon name="close" /></button>
        </header>
        <div class="modal-body">
          <p v-if="!initialStatuses.length" id="create-no-initial" class="error field-error" role="alert">
            Este workflow não tem status inicial. A criação de task está bloqueada.
          </p>
          <label class="field" for="create-summary">
            <span>Summary</span>
            <input
              id="create-summary"
              ref="summaryInput"
              type="text"
              v-model="draft.summary"
              :aria-invalid="Boolean(summaryError)"
              :aria-describedby="summaryError ? 'create-summary-error' : undefined"
            />
          </label>
          <p v-if="summaryError" id="create-summary-error" class="error field-error" role="alert">
            {{ summaryError }}
          </p>
          <label class="field" for="create-description">
            <span>Description</span>
            <textarea id="create-description" rows="4" v-model="draft.description"></textarea>
          </label>
          <label class="field" for="create-type">
            <span>Type</span>
            <select id="create-type" v-model="draft.typeId">
              <option v-for="type in issueTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
          <label class="field" for="create-status">
            <span>Status</span>
            <select
              id="create-status"
              v-model="draft.statusId"
              :disabled="!initialStatuses.length"
              :aria-invalid="Boolean(statusError)"
              :aria-describedby="statusError ? 'create-status-error' : undefined"
            >
              <option v-for="status in initialStatuses" :key="status.id" :value="status.id">
                {{ status.name }}
              </option>
            </select>
          </label>
          <p v-if="statusError" id="create-status-error" class="error field-error" role="alert">
            {{ statusError }}
          </p>
          <label class="field" for="create-assignee">
            <span>Assignee</span>
            <select id="create-assignee" v-model="draft.assigneeId">
              <option :value="null">Unassigned</option>
              <option v-for="agent in boardStore.state.agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
            </select>
          </label>
          <p v-if="error" id="create-form-error" class="error" role="alert">{{ error }}</p>
        </div>
        <footer class="modal-footer">
          <button class="button ghost" @click="editing = false">Cancel</button>
          <button class="button" :disabled="!initialStatuses.length" @click="save">Create</button>
        </footer>
      </div>
    </div>
  </div>
</template>
