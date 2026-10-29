<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { boardStore } from '../store/board.js'
import BoardColumn from '../components/BoardColumn.vue'
import IssuePanel from '../components/IssuePanel.vue'
import AppIcon from '../components/AppIcon.vue'
import FieldLimit from '../components/FieldLimit.vue'
import FieldValueInput from '../components/FieldValueInput.vue'
import { toast } from '../store/toast.js'
import { DESCRIPTION_LIMIT, countCharacters, limitError, limitExceeded } from '../../shared/limits.js'

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
const descriptionError = ref('')
const fieldErrors = ref({})
const descriptionOver = computed(() => limitExceeded(countCharacters(draft.value.description), DESCRIPTION_LIMIT))

const board = computed(() => boardStore.board.value)
const columns = computed(() => boardStore.boardColumns.value)
const statuses = computed(() => boardStore.boardStatuses.value)
const issueTypes = computed(() => boardStore.boardIssueTypes.value)
const repositories = computed(() => boardStore.state.sources)
const selectableTypes = computed(() =>
  issueTypes.value.filter((type) =>
    statuses.value.some((status) => status.issueTypeId === type.id && status.type === 'initial'),
  ),
)
const initialStatuses = computed(() =>
  statuses.value.filter(
    (status) => status.type === 'initial' && status.issueTypeId === draft.value.typeId,
  ),
)
const typeFields = computed(
  () => issueTypes.value.find((type) => type.id === draft.value.typeId)?.fields ?? [],
)
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
    typeId: selectableTypes.value[0]?.id ?? null,
    statusId: '',
    assigneeId: null,
    repositoryId: '',
    fields: {},
  }
  error.value = ''
  summaryError.value = ''
  statusError.value = ''
  descriptionError.value = ''
  fieldErrors.value = {}
  draft.value.statusId = initialStatuses.value[0]?.id || ''
  editing.value = true
  await nextTick()
  summaryInput.value?.focus()
}

watch(() => draft.value.summary, () => { summaryError.value = '' })
watch(() => draft.value.statusId, () => { statusError.value = '' })
watch(() => draft.value.description, () => { descriptionError.value = '' })
watch(
  () => draft.value.typeId,
  () => {
    draft.value.fields = {}
    draft.value.statusId = initialStatuses.value[0]?.id || ''
    fieldErrors.value = {}
  },
)
watch(selectableTypes, (types) => {
  if (!editing.value || types.some((type) => type.id === draft.value.typeId)) {
    return
  }
  draft.value.typeId = types[0]?.id ?? null
  draft.value.fields = {}
  draft.value.statusId = ''
})
watch(initialStatuses, (initials) => {
  if (!editing.value) return
  if (initials.some((status) => status.id === draft.value.statusId)) return
  draft.value.statusId = initials[0]?.id || ''
})

function applyFailure(failure) {
  if (failure.field === 'summary') {
    summaryError.value = failure.message
    return
  }
  if (failure.field === 'statusId') {
    statusError.value = failure.message
    return
  }
  if (failure.field === 'description') {
    descriptionError.value = failure.message
    return
  }
  if (typeof failure.field === 'string' && failure.field.startsWith('fields.')) {
    fieldErrors.value = { ...fieldErrors.value, [failure.field.slice('fields.'.length)]: failure.message }
    return
  }
  error.value = failure.message || 'Não foi possível criar a task.'
}

async function save() {
  error.value = ''
  summaryError.value = ''
  statusError.value = ''
  descriptionError.value = ''
  fieldErrors.value = {}
  if (!draft.value.summary) {
    summaryError.value = 'O resumo é obrigatório.'
    summaryInput.value?.focus()
    return
  }
  if (!draft.value.typeId) {
    error.value = 'Nenhum tipo de issue disponível neste workflow.'
    return
  }
  if (!initialStatuses.value.length) return
  if (!draft.value.statusId) {
    statusError.value = 'Selecione um status inicial.'
    return
  }
  if (descriptionOver.value) {
    descriptionError.value = limitError('description', countCharacters(draft.value.description), DESCRIPTION_LIMIT)
    return
  }
  try {
    await boardStore.createIssue({
      issueTypeId: draft.value.typeId,
      summary: draft.value.summary,
      description: draft.value.description,
      ...(draft.value.assigneeId ? { assigneeId: draft.value.assigneeId } : {}),
      ...(draft.value.repositoryId ? { repositoryId: draft.value.repositoryId } : {}),
      ...(typeFields.value.length ? { fields: draft.value.fields } : {}),
    })
  } catch (failure) {
    applyFailure(failure)
    return
  }
  editing.value = false
}

const draggingColumnId = ref(null)
const columnDropTargetId = ref(null)

function onColumnDragStart(column) {
  draggingColumnId.value = column.id
  columnDropTargetId.value = null
}

function onColumnHeaderOver(column) {
  if (!draggingColumnId.value || draggingColumnId.value === column.id) {
    return
  }
  columnDropTargetId.value = column.id
}

function onColumnDragEnd() {
  draggingColumnId.value = null
  columnDropTargetId.value = null
}

async function onColumnDrop(targetColumn) {
  const draggedId = draggingColumnId.value
  onColumnDragEnd()
  if (!draggedId || draggedId === targetColumn.id) {
    return
  }
  const ordered = columns.value.slice()
  const from = ordered.findIndex((column) => column.id === draggedId)
  const to = ordered.findIndex((column) => column.id === targetColumn.id)
  if (from < 0 || to < 0) {
    return
  }
  const [moved] = ordered.splice(from, 1)
  ordered.splice(to, 0, moved)
  try {
    await boardStore.reorderColumns(board.value.id, ordered.map((column) => column.id))
  } catch (problem) {
    toast.error(problem.message)
  }
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
  await boardStore.moveIssue(id, { columnId, statusId, position: index })
  endDrag()
}
</script>

<template>
  <div class="board-layout">
    <div class="board-main">
      <div class="board-toolbar">
        <strong>{{ board?.name || 'Board' }}</strong>
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
          :column-dragging="draggingColumnId === column.id"
          :column-drop-target="columnDropTargetId === column.id"
          @over="onColumnOver"
          @drop="drop(column.id, null)"
          @drag-start="startDrag"
          @drag-end="endDrag"
          @card-over="(issueId) => onCardOver(column.id, issueId)"
          @card-drop="(issueId) => drop(column.id, issueId)"
          @column-drag-start="onColumnDragStart"
          @column-over="onColumnHeaderOver"
          @column-drag-end="onColumnDragEnd"
          @column-drop="onColumnDrop"
        />
      </div>
    </div>

    <IssuePanel />

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card new-task-modal">
        <header class="modal-header">
          <h3>New task</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="editing = false"><AppIcon name="close" size="20" /></button>
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
          <div class="field">
            <label for="create-description">Description</label>
            <textarea
              id="create-description"
              rows="4"
              v-model="draft.description"
              aria-describedby="create-description-limit"
              :aria-invalid="descriptionOver || Boolean(descriptionError)"
            ></textarea>
            <FieldLimit
              id="create-description-limit"
              field="description"
              :text="draft.description"
              :limit="DESCRIPTION_LIMIT"
              :error="descriptionError"
            />
          </div>
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
          <FieldValueInput
            v-for="field in typeFields"
            :key="field.id"
            v-model="draft.fields[field.key]"
            :field="field"
          >
            <small v-if="fieldErrors[field.key]" class="error">{{ fieldErrors[field.key] }}</small>
          </FieldValueInput>
          <label class="field" for="create-assignee">
            <span>Assignee</span>
            <select id="create-assignee" v-model="draft.assigneeId">
              <option :value="null">Unassigned</option>
              <option v-for="agent in boardStore.state.agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
            </select>
          </label>
          <label class="field" for="create-repository">
            <span>Repository (optional)</span>
            <select id="create-repository" v-model="draft.repositoryId">
              <option value="">No repository (scratch)</option>
              <option v-for="repository in repositories" :key="repository.id" :value="repository.id">
                {{ repository.name }}
              </option>
            </select>
          </label>
          <p v-if="!draft.repositoryId" class="muted small">
            Tasks without a repository run in a scratch workdir and are not executed by agents.
          </p>
          <p v-if="error" id="create-form-error" class="error" role="alert">{{ error }}</p>
        </div>
        <footer class="modal-footer">
          <button class="button ghost" @click="editing = false">Cancel</button>
          <button class="button" :disabled="!initialStatuses.length || descriptionOver" @click="save"><AppIcon name="check" size="16" /> Create</button>
        </footer>
      </div>
    </div>
  </div>
</template>
