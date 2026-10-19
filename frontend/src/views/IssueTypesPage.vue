<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { boardStore } from '../store/board.js'
import { dialog } from '../store/dialog.js'
import { toast } from '../store/toast.js'
import AppIcon from './../components/AppIcon.vue'
import { isSimulated } from '../api/index.js'

const route = useRoute()
const types = computed(() => boardStore.issueTypesOf(route.params.workflowId))
const editing = ref(false)
const draft = ref({})
const error = ref('')

const slugify = (text) =>
  String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

function openCreate() {
  draft.value = {
    workflowId: route.params.workflowId,
    name: '',
    level: 1,
    fields: [
      { key: 'summary', label: 'Summary', type: 'text', required: true, system: true },
      { key: 'description', label: 'Description', type: 'textarea', required: true, system: true },
    ],
  }
  error.value = ''
  editing.value = true
}

function openEdit(type) {
  draft.value = JSON.parse(JSON.stringify(type))
  draft.value.__existing = true
  error.value = ''
  editing.value = true
}

function addField() {
  draft.value.fields.push({ key: '', label: '', type: 'text', required: false })
}

function removeField(index) {
  draft.value.fields.splice(index, 1)
}

function normalize() {
  draft.value.fields.forEach((field) => {
    if (!field.key) {
      field.key = slugify(field.label)
    }
    if (field.type === 'select' && typeof field.options === 'string') {
      field.options = field.options.split(',').map((option) => option.trim()).filter(Boolean)
    }
  })
}

async function save() {
  error.value = ''
  if (!draft.value.name) {
    error.value = 'Name is required'
    return
  }
  normalize()
  try {
    await boardStore.saveEntity('issueTypes', draft.value)
    editing.value = false
    toast.success(`${draft.value.name} saved.`)
  } catch (problem) {
    error.value = problem.message
  }
}

async function remove(type) {
  const ok = await dialog.confirm({
    title: 'Delete?',
    message: `"${type.name}" will be removed.`,
    confirmLabel: 'Delete',
    danger: true,
  })
  if (!ok) return
  try {
    await boardStore.removeEntity('issueTypes', type.id)
    toast.success(`"${type.name}" deleted.`)
  } catch (problem) {
    toast.error(problem.message)
  }
}
</script>

<template>
  <div class="crud">
    <header class="crud-header">
      <h2>Issue types</h2>
      <span v-if="isSimulated('issueTypes')" class="badge sim"><span class="sim-dot sm"></span> Prototype</span>
      <div class="spacer"></div>
      <button class="button" @click="openCreate"><AppIcon name="plus" size="16" /> New type</button>
    </header>

    <div class="table">
      <div class="table-row head"><span>Name</span><span>Level</span><span>Fields</span><span class="actions">Actions</span></div>
      <div class="table-row" v-for="type in types" :key="type.id">
        <span>{{ type.name }}</span>
        <span>{{ type.level }}</span>
        <span>{{ type.fields.length }}</span>
        <span class="actions">
          <button class="icon-btn" title="Edit" aria-label="Edit" @click="openEdit(type)"><AppIcon name="edit" size="14" /></button>
          <button class="icon-btn" title="Delete" aria-label="Delete" @click="remove(type)"><AppIcon name="delete" size="14" /></button>
        </span>
      </div>
    </div>

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>{{ draft.id ? 'Edit issue type' : 'New issue type' }}</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="editing = false"><AppIcon name="close" size="20" /></button>
        </header>

        <div class="modal-body">
          <label class="field"><span>Name</span><input type="text" v-model="draft.name" /></label>
          <label class="field">
            <span>Level (0 = epic, 1 = task, 2 = subtask)</span>
            <select v-model.number="draft.level"><option :value="0">0</option><option :value="1">1</option><option :value="2">2</option></select>
          </label>

          <h4 class="field-label">Fields</h4>
          <div class="field-row" v-for="(field, index) in draft.fields" :key="index">
            <input type="text" placeholder="Label" v-model="field.label" :disabled="field.system" />
            <select v-model="field.type" :disabled="field.system">
              <option value="text">Text</option>
              <option value="textarea">Textarea</option>
              <option value="number">Number</option>
              <option value="select">Select</option>
            </select>
            <input v-if="field.type === 'select'" type="text" placeholder="a, b, c" v-model="field.options" />
            <label class="inline"><input type="checkbox" v-model="field.required" /> required</label>
            <button class="icon-btn" title="Remove" :disabled="field.system" aria-label="Remove" @click="removeField(index)">
              <AppIcon name="delete" size="14" />
            </button>
          </div>
          <button class="button ghost" @click="addField"><AppIcon name="plus" size="16" /> Add field</button>

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
