<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()
const statusName = (id) => (id === '*' ? 'Any status' : boardStore.status(id)?.name || id)
const issueTypes = computed(() => boardStore.state.issueTypes.filter((type) => type.workflowId === route.params.workflowId))
const statusesFor = (typeId) => boardStore.statusesOf(route.params.workflowId).filter((status) => status.issueTypeId === typeId)
const transitionIssueType = (transition) => {
  const from = transition.fromStatusId ?? transition.from
  const status = from === '*' ? boardStore.status(transition.toStatusId ?? transition.to) : boardStore.status(from)
  return status?.issueTypeId || ''
}
const issueTypeName = (transition) => {
  const typeId = transitionIssueType(transition)
  return boardStore.state.issueTypes.find((type) => type.id === typeId)?.name || ''
}
const fromOptions = (draft) => [
  { value: '*', label: 'Any status' },
  ...statusesFor(draft.typeId).map((status) => ({ value: status.id, label: status.name })),
]
const toOptions = (draft) => statusesFor(draft.typeId).map((status) => ({ value: status.id, label: status.name }))

const schema = {
  collection: 'transitions',
  singular: 'transition',
  title: 'Transitions',
  feature: 'transitions',
  items: () => boardStore.transitionsOf(route.params.workflowId),
  inject: () => ({ workflowId: route.params.workflowId }),
  columns: [
    { key: 'name', label: 'Transition', format: (row) => row.name || '—' },
    { key: 'issueType', label: 'Issue type', format: (row) => issueTypeName(row) },
    { key: 'from', label: 'From', format: (row) => statusName(row.from) },
    { key: 'to', label: 'To', format: (row) => statusName(row.to) },
    { key: 'type', label: 'Allowed for' },
  ],
  fields: [
    { key: 'name', label: 'Transition name', type: 'text' },
    {
      key: 'typeId',
      label: 'Issue type',
      type: 'select',
      required: true,
      options: () => issueTypes.value.map((type) => ({ value: type.id, label: type.name })),
    },
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
      options: toOptions,
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
  prepare: (row) => ({ ...row, typeId: transitionIssueType(row) }),
  defaults: () => ({ name: '', typeId: issueTypes.value[0]?.id ?? '', from: '', to: '', type: 'both' }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
