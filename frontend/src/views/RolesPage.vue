<script setup>
import CrudPage from '../components/CrudPage.vue'

const schema = {
  collection: 'roles',
  singular: 'role',
  title: 'Roles',
  feature: 'roles',
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'description', label: 'Description' },
    { key: 'unblock', label: 'Unblock', format: (row) => (row.unblock ? 'yes' : 'no') },
    { key: 'interactive', label: 'Interactive', format: (row) => (row.interactive ? 'yes' : 'no') },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'description', label: 'Description', type: 'textarea' },
    { key: 'unblock', label: 'Handles blocked items', type: 'checkbox', hint: 'Unblocks impediments' },
    { key: 'interactive', label: 'Acts on new comments', type: 'checkbox', hint: 'Interactive mode' },
  ],
  defaults: () => ({ name: '', description: '', unblock: false, interactive: false }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
