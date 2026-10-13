<script setup>
import { computed, ref } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'
import { isSimulated } from '../api/index.js'

const props = defineProps({ schema: { type: Object, required: true } })

const items = computed(() => {
  const base = props.schema.items ? props.schema.items() : boardStore.state[props.schema.collection] || []
  return props.schema.filter ? base.filter(props.schema.filter) : base
})
const editing = ref(false)
const draft = ref({})
const error = ref('')

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
  error.value = ''
  editing.value = true
}

function cellValue(row, column) {
  return column.format ? column.format(row) : row[column.key]
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
  } catch (problem) {
    error.value = problem.message
  }
}

async function remove(row) {
  if (!window.confirm(`Delete "${row.name ?? row.id}"?`)) {
    return
  }
  await boardStore.removeEntity(props.schema.collection, row.id)
}
</script>

<template>
  <div class="crud">
    <header class="crud-header">
      <h2>{{ schema.title }}</h2>
      <span v-if="isSimulated(schema.feature)" class="badge sim"><span class="sim-dot sm"></span> Prototype</span>
      <div class="spacer"></div>
      <button class="button" @click="openCreate"><AppIcon name="plus" size="16" /> New {{ schema.singular }}</button>
    </header>

    <div class="table">
      <div class="table-row head">
        <span v-for="column in schema.columns" :key="column.key">{{ column.label }}</span>
        <span class="actions">Actions</span>
      </div>
      <div class="table-row" v-for="row in items" :key="row.id">
        <span v-for="column in schema.columns" :key="column.key">
          <span v-if="column.type === 'color'" class="swatch" :style="{ background: row[column.key] }"></span>
          <template v-else>{{ cellValue(row, column) }}</template>
        </span>
        <span class="actions">
          <button class="icon-btn" title="Edit" @click="openEdit(row)"><AppIcon name="profiles" size="15" /></button>
          <button class="icon-btn" title="Delete" @click="remove(row)"><AppIcon name="close" size="15" /></button>
        </span>
      </div>
      <p v-if="!items.length" class="muted pad">Nothing here yet.</p>
    </div>

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>{{ draft.id ? `Edit ${schema.singular}` : `New ${schema.singular}` }}</h3>
          <button class="icon-btn" @click="editing = false"><AppIcon name="close" /></button>
        </header>

        <div class="modal-body">
          <label class="field" v-for="field in schema.fields" :key="field.key">
            <span>{{ field.label }}</span>

            <input v-if="field.type === 'color'" type="color" v-model="draft[field.key]" />
            <input v-else-if="field.type === 'number'" type="number" v-model.number="draft[field.key]" />
            <input v-else-if="field.type === 'text' || !field.type" type="text" v-model="draft[field.key]" />
            <textarea v-else-if="field.type === 'textarea'" rows="3" v-model="draft[field.key]"></textarea>

            <select v-else-if="field.type === 'select'" v-model="draft[field.key]">
              <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <label v-else-if="field.type === 'checkbox'" class="inline">
              <input type="checkbox" v-model="draft[field.key]" /> {{ field.hint || 'Enabled' }}
            </label>

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
          <button class="button" @click="save">Save</button>
        </footer>
      </div>
    </div>
  </div>
</template>
