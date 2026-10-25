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

function toggleStatus(column, statusId) {
  const current = column.statusIds || []
  column.statusIds = current.includes(statusId) ? current.filter((id) => id !== statusId) : [...current, statusId]
}
</script>

<template>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="board-editor-title">
    <header class="modal-header">
      <h3 id="board-editor-title">{{ draft.__existing ? 'Edit board' : 'New board' }}</h3>
      <button class="icon-btn" title="Close" aria-label="Close" @click="emit('close')"><AppIcon name="close" size="20" /></button>
    </header>

    <div class="modal-body">
      <p class="muted small">Columns are managed on the board page: create them there, set their WIP limit and the statuses they hold.</p>
      <label class="field"><span>Name</span><input type="text" v-model="draft.name" /></label>
      <label class="field">
        <span>Workflow</span>
        <select v-model="draft.workflowId">
          <option v-for="workflow in workflows" :key="workflow.id" :value="workflow.id">{{ workflow.name }}</option>
        </select>
      </label>
      <p class="error" v-if="error">{{ error }}</p>
    </div>

    <footer class="modal-footer">
      <button class="button ghost" @click="emit('close')">Cancel</button>
      <button class="button" @click="emit('save')">Save</button>
    </footer>
  </div>
</template>
