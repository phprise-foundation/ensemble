<script setup>
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()

const schema = {
  collection: 'statuses',
  singular: 'status',
  title: 'Statuses',
  feature: 'statuses',
  items: () => boardStore.statusesOf(route.params.workflowId),
  inject: () => ({ workflowId: route.params.workflowId }),
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'type', label: 'Type' },
    { key: 'color', label: 'Color', type: 'color' },
    {
      key: 'roleIds',
      label: 'Roles',
      format: (row) => (row.roleIds || []).map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') || '—',
    },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    {
      key: 'type',
      label: 'Type',
      type: 'select',
      options: () => [
        { value: 'initial', label: 'Initial' },
        { value: 'transitional', label: 'Transitional' },
        { value: 'final', label: 'Final' },
      ],
    },
    { key: 'color', label: 'Color', type: 'color' },
    {
      key: 'roleIds',
      label: 'Roles that can pull from this status',
      type: 'multiselect',
      options: () => boardStore.state.roles.map((role) => ({ value: role.id, label: role.name })),
    },
  ],
  defaults: () => ({ name: '', type: 'transitional', color: '#4c9aff', roleIds: [] }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
