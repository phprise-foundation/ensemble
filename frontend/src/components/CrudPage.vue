<script setup>
import { computed, ref } from 'vue'
import { boardStore } from '../store/board.js'
import { dialog } from '../store/dialog.js'
import { toast } from '../store/toast.js'
import AppIcon from './AppIcon.vue'
import ToggleSwitch from './ToggleSwitch.vue'
import MarkdownTextarea from './MarkdownTextarea.vue'
import { plainText } from '../lib/plain-text.js'

const props = defineProps({ schema: { type: Object, required: true } })

const items = computed(() => {
  const base = props.schema.items ? props.schema.items() : boardStore.state[props.schema.collection] || []
  return props.schema.filter ? base.filter(props.schema.filter) : base
})
const editing = ref(false)
const draft = ref({})
const error = ref('')
const dragRowId = ref(null)
const dropRowId = ref(null)
const canReorder = computed(() => typeof props.schema.reorder === 'function')

function optionsFor(field) {
  return typeof field.options === 'function' ? field.options() : field.options || []
}

function openCreate() {
  draft.value = { ...props.schema.defaults(), ...(props.schema.inject ? props.schema.inject() : {}) }
  error.value = ''
  editing.value = true
}

function openEdit(row) {
  draft.value = JSON.parse(JSON.stringify(row))
  draft.value.__existing = true
  error.value = ''
  editing.value = true
}

function cellValue(row, column) {
  return column.format ? column.format(row) : row[column.key]
}

function isMarkdownColumn(column) {
  return column.type === 'markdown'
    || (props.schema.fields ?? []).some((field) => field.key === column.key && field.type === 'textarea')
}

function fieldFor(column) {
  return (props.schema.fields ?? []).find((field) => field.key === column.key)
}

function isBooleanColumn(column) {
  return column.type === 'boolean' || fieldFor(column)?.type === 'checkbox'
}

function titleKey() {
  return props.schema.titleKey ?? 'name'
}

function cardTitle(row) {
  return row[titleKey()] ?? row.id
}

function descriptionText(row) {
  const column = props.schema.columns.find((item) => isMarkdownColumn(item))
  return column ? plainText(cellValue(row, column)) : ''
}

function metaColumns() {
  return props.schema.columns.filter((column) => (
    column.key !== titleKey() && !isMarkdownColumn(column) && !isBooleanColumn(column)
  ))
}

function trueFlags(row) {
  return props.schema.columns
    .filter((column) => isBooleanColumn(column) && Boolean(row[column.key]))
    .map((column) => ({ key: column.key, label: column.label }))
}

function isChecked(field, value) {
  return (draft.value[field.key] || []).includes(value)
}

function toggle(field, value) {
  const current = draft.value[field.key] || []
  draft.value[field.key] = current.includes(value) ? current.filter((item) => item !== value) : [...current, value]
}

async function save() {
  error.value = ''
  for (const field of props.schema.fields) {
    if (field.required && (draft.value[field.key] === '' || draft.value[field.key] === null || draft.value[field.key] === undefined)) {
      error.value = `${field.label} is required`
      return
    }
  }
  try {
    await boardStore.saveEntity(props.schema.collection, draft.value)
    editing.value = false
    const label = draft.value.name ?? draft.value.id ?? props.schema.singular
    toast.success(`${label} saved.`)
  } catch (problem) {
    if (problem.field) {
      error.value = problem.message
      return
    }
    toast.error(problem.message)
  }
}

function onRowDragStart(row) {
  if (!canReorder.value) return
  dragRowId.value = row.id
  dropRowId.value = null
}

function onRowDragOver(row) {
  if (!canReorder.value || !dragRowId.value || dragRowId.value === row.id) return
  dropRowId.value = row.id
}

function onRowDragEnd() {
  dragRowId.value = null
  dropRowId.value = null
}

async function onRowDrop(row) {
  const draggedId = dragRowId.value
  onRowDragEnd()
  if (!canReorder.value || !draggedId || draggedId === row.id) return
  const ordered = items.value.slice()
  const from = ordered.findIndex((item) => item.id === draggedId)
  const to = ordered.findIndex((item) => item.id === row.id)
  if (from < 0 || to < 0) return
  const [moved] = ordered.splice(from, 1)
  ordered.splice(to, 0, moved)
  try {
    await props.schema.reorder(ordered.map((item) => item.id))
  } catch (problem) {
    toast.error(problem.message)
  }
}

async function remove(row) {
  const label = row.name ?? row.id
  const ok = await dialog.confirm({
    title: 'Delete?',
    message: `"${label}" will be removed.`,
    confirmLabel: 'Delete',
    danger: true,
  })
  if (!ok) return
  try {
    await boardStore.removeEntity(props.schema.collection, row.id)
    toast.success(`"${label}" deleted.`)
  } catch (problem) {
    toast.error(problem.message)
  }
}
</script>

<template>
  <div class="crud">
    <header class="crud-header">
      <h2>{{ schema.title }}</h2>
      <div class="spacer"></div>
      <button class="button" @click="openCreate"><AppIcon name="plus" size="16" /> New {{ schema.singular }}</button>
    </header>

    <div class="crud-cards">
      <article
        v-for="row in items"
        :key="row.id"
        class="crud-card table-row"
        :class="{ dragging: dragRowId === row.id, 'drop-target': dropRowId === row.id }"
        :draggable="canReorder"
        :title="canReorder ? 'Drag to reorder' : undefined"
        @dragstart="onRowDragStart(row)"
        @dragover.prevent="onRowDragOver(row)"
        @dragend="onRowDragEnd"
        @drop.prevent="onRowDrop(row)"
      >
        <header class="crud-card-head">
          <h3 class="crud-card-title">{{ cardTitle(row) }}</h3>
          <span class="actions">
            <button class="icon-btn" title="Edit" aria-label="Edit" @click="openEdit(row)"><AppIcon name="edit" size="14" /></button>
            <button class="icon-btn" title="Delete" aria-label="Delete" @click="remove(row)"><AppIcon name="delete" size="14" /></button>
          </span>
        </header>

        <p v-if="descriptionText(row)" class="crud-card-desc">{{ descriptionText(row) }}</p>

        <dl v-if="metaColumns().length" class="crud-card-meta">
          <template v-for="column in metaColumns()" :key="column.key">
            <dt>{{ column.label }}</dt>
            <dd>
              <span v-if="column.type === 'color'" class="swatch" :style="{ background: row[column.key] }"></span>
              <template v-else>{{ cellValue(row, column) }}</template>
            </dd>
          </template>
        </dl>

        <div v-if="trueFlags(row).length" class="crud-card-flags">
          <span v-for="flag in trueFlags(row)" :key="flag.key" class="chip">{{ flag.label }}</span>
        </div>
      </article>
      <p v-if="!items.length" class="muted pad">Nothing here yet.</p>
    </div>

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>{{ draft.id ? `Edit ${schema.singular}` : `New ${schema.singular}` }}</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="editing = false"><AppIcon name="close" size="20" /></button>
        </header>

        <div class="modal-body">
          <label class="field" v-for="field in schema.fields" :key="field.key">
            <span>{{ field.label }}</span>

            <input v-if="field.type === 'color'" type="color" v-model="draft[field.key]" />
            <input v-else-if="field.type === 'number'" type="number" v-model.number="draft[field.key]" />
            <input v-else-if="field.type === 'text' || !field.type" type="text" v-model="draft[field.key]" />
            <MarkdownTextarea v-else-if="field.type === 'textarea'" v-model="draft[field.key]" :rows="5" :aria-label="field.label" />

            <select v-else-if="field.type === 'select'" v-model="draft[field.key]">
              <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <div v-else-if="field.type === 'checkbox'" class="toggle-field">
              <span class="toggle-text">
                <span>{{ field.label }}</span>
                <small v-if="field.hint" class="muted">{{ field.hint }}</small>
              </span>
              <ToggleSwitch v-model="draft[field.key]" :label="field.label" />
            </div>

            <div v-else-if="field.type === 'multiselect'" class="checks">
              <label v-for="option in optionsFor(field)" :key="option.value" class="inline">
                <input type="checkbox" :checked="isChecked(field, option.value)" @change="toggle(field, option.value)" />
                {{ option.label }}
              </label>
              <span v-if="!optionsFor(field).length" class="muted small">No options</span>
            </div>
          </label>

          <p v-if="error" class="error">{{ error }}</p>
        </div>

        <footer class="modal-footer">
          <button class="button ghost" @click="editing = false">Cancel</button>
          <button class="button" @click="save"><AppIcon name="check" size="16" /> Save</button>
        </footer>
      </div>
    </div>
  </div>
</template>
