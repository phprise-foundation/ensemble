<script setup>
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const schema = {
  collection: 'columns',
  singular: 'column',
  title: 'Columns',
  feature: 'columns',
  columns: [
    { key: 'name', label: 'Name' },
    {
      key: 'statusIds',
      label: 'Statuses',
      format: (row) => (row.statusIds || []).map((id) => boardStore.status(id)?.name).filter(Boolean).join(', ') || '—',
    },
    { key: 'wip', label: 'WIP' },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    {
      key: 'statusIds',
      label: 'Statuses (at least one)',
      type: 'multiselect',
      options: () => boardStore.state.statuses.map((status) => ({ value: status.id, label: status.name })),
    },
    { key: 'wip', label: 'WIP limit (optional)', type: 'number' },
  ],
  defaults: () => ({ name: '', statusIds: [], wip: null }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
