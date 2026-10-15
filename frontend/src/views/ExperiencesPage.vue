<script setup>
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const schema = {
  collection: 'experiences',
  singular: 'experience',
  title: 'Experiences',
  feature: 'experiences',
  columns: [
    { key: 'title', label: 'Title' },
    { key: 'agentId', label: 'Agent', format: (row) => boardStore.agent(row.agentId)?.name || '—' },
    { key: 'body', label: 'Story' },
  ],
  fields: [
    {
      key: 'agentId',
      label: 'Agent',
      type: 'select',
      required: true,
      options: () => boardStore.state.agents.map((agent) => ({ value: agent.id, label: agent.name })),
    },
    { key: 'title', label: 'Title', type: 'text', required: true },
    { key: 'body', label: 'Story', type: 'textarea' },
  ],
  defaults: () => ({ agentId: '', title: '', body: '' }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
