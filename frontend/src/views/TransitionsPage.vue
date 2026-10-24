<script setup>
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()
const statusName = (id) => (id === '*' ? 'Any status' : boardStore.status(id)?.name || id)

const fromOptions = () =>
  boardStore.statusesOf(route.params.workflowId).map((status) => ({ value: status.id, label: status.name }))

const schema = {
  collection: 'transitions',
  singular: 'transition',
  title: 'Transitions',
  feature: 'transitions',
  items: () => boardStore.transitionsOf(route.params.workflowId),
  inject: () => ({ workflowId: route.params.workflowId }),
  columns: [
    { key: 'from', label: 'From', format: (row) => statusName(row.from) },
    { key: 'to', label: 'To', format: (row) => statusName(row.to) },
    { key: 'type', label: 'Type' },
  ],
  fields: [
    {
      key: 'from',
      label: 'From',
      type: 'select',
      required: true,
      options: fromOptions,
    },
    {
      key: 'to',
      label: 'To',
      type: 'select',
      required: true,
      options: () => boardStore.statusesOf(route.params.workflowId).map((status) => ({ value: status.id, label: status.name })),
    },
    {
      key: 'type',
      label: 'Allowed for',
      type: 'select',
      options: () => [
        { value: 'user', label: 'User transition' },
        { value: 'agent', label: 'Agent transition' },
        { value: 'both', label: 'Both' },
      ],
    },
  ],
  defaults: () => ({ from: '', to: '', type: 'both' }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
