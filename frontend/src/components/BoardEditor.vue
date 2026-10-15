<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'
import { boardStore } from '../store/board.js'

const props = defineProps({
  draft: { type: Object, required: true },
  error: { type: String, default: '' },
})

const emit = defineEmits(['save', 'close'])

const workflows = computed(() => boardStore.workflowsOf(props.draft.workspaceId))
const statuses = computed(() => boardStore.statusesOf(props.draft.workflowId))

function addColumn() {
  props.draft.columns = props.draft.columns || []
  props.draft.columns.push({ id: '', name: '', wip: null, statusIds: [] })
}

function removeColumn(index) {
  props.draft.columns.splice(index, 1)
}

function toggleStatus(column, statusId) {
  const current = column.statusIds || []
  column.statusIds = current.includes(statusId) ? current.filter((id) => id !== statusId) : [...current, statusId]
}

function toggleFilter(statusId) {
  props.draft.filters = props.draft.filters || { statusIds: [] }
  const current = props.draft.filters.statusIds || []
  props.draft.filters.statusIds = current.includes(statusId) ? current.filter((id) => id !== statusId) : [...current, statusId]
}
</script>

<template>
  <div class="modal" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>{{ draft.id && draft.name ? 'Edit board' : 'New board' }}</h3>
        <button class="icon-btn" @click="emit('close')"><AppIcon name="close" /></button>
      </header>

      <div class="modal-body">
        <label class="field">
          <span>ID (3 to 6 letters)</span>
          <input type="text" maxlength="6" v-model="draft.id" :disabled="Boolean(draft.__existing)" />
        </label>
        <label class="field"><span>Name</span><input type="text" v-model="draft.name" /></label>
        <label class="field">
          <span>Workflow</span>
          <select v-model="draft.workflowId">
            <option v-for="workflow in workflows" :key="workflow.id" :value="workflow.id">{{ workflow.name }}</option>
          </select>
        </label>

        <h4 class="field-label">Columns</h4>
        <div class="column-editor" v-for="(column, index) in draft.columns" :key="index">
          <input type="text" placeholder="Name" v-model="column.name" />
          <input class="wip" type="number" placeholder="WIP" v-model.number="column.wip" />
          <div class="checks">
            <label v-for="status in statuses" :key="status.id" class="inline">
              <input type="checkbox" :checked="(column.statusIds || []).includes(status.id)" @change="toggleStatus(column, status.id)" />
              {{ status.name }}
            </label>
            <span v-if="!statuses.length" class="muted small">Pick a workflow with statuses first</span>
          </div>
          <button class="icon-btn" title="Remove" @click="removeColumn(index)"><AppIcon name="close" size="15" /></button>
        </div>
        <button class="button ghost" @click="addColumn"><AppIcon name="plus" size="15" /> Add column</button>

        <h4 class="field-label">Filters (statuses shown; empty = all)</h4>
        <div class="checks">
          <label v-for="status in statuses" :key="status.id" class="inline">
            <input type="checkbox" :checked="((draft.filters || {}).statusIds || []).includes(status.id)" @change="toggleFilter(status.id)" />
            {{ status.name }}
          </label>
        </div>

        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <footer class="modal-footer">
        <button class="button ghost" @click="emit('close')">Cancel</button>
        <button class="button" @click="emit('save')">Save</button>
      </footer>
    </div>
  </div>
</template>
