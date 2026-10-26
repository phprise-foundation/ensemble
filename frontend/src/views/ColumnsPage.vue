<script setup>
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()

const board = () => boardStore.state.boards.find((candidate) => candidate.id === route.params.boardId) ?? null

const schema = {
  collection: 'columns',
  singular: 'column',
  title: 'Columns',
  feature: null,
  items: () =>
    boardStore.state.columns
      .filter((column) => column.boardId === route.params.boardId)
      .slice()
      .sort((a, b) => (a.position ?? 0) - (b.position ?? 0)),
  reorder: (columnIds) => boardStore.reorderColumns(route.params.boardId, columnIds),
  inject: () => ({ boardId: route.params.boardId }),
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'position', label: 'Position' },
    {
      key: 'wip',
      label: 'WIP limit',
      format: (row) => (row.wip === null || row.wip === undefined ? '—' : row.wip),
    },
    {
      key: 'statusIds',
      label: 'Statuses',
      format: (row) => (row.statusIds || []).map((id) => boardStore.status(id)?.name).filter(Boolean).join(', ') || '—',
    },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'position', label: 'Position', type: 'number' },
    { key: 'wip', label: 'WIP limit (empty = unlimited)', type: 'number' },
    {
      key: 'statusIds',
      label: 'Statuses in this column',
      type: 'multiselect',
      options: () => boardStore.statusesOf(board()?.workflowId ?? '').map((status) => ({ value: status.id, label: status.name })),
    },
  ],
  defaults: () => ({
    name: '',
    wip: null,
    statusIds: [],
    position: boardStore.state.columns
      .filter((column) => column.boardId === route.params.boardId)
      .reduce((max, column) => Math.max(max, column.position ?? 0), -1) + 1,
  }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
