<script setup>
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()

const schema = {
  collection: 'roles',
  singular: 'role',
  title: 'Roles',
  feature: 'roles',
  items: () => boardStore.rolesOf(route.params.teamId),
  inject: () => ({ teamId: route.params.teamId }),
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'description', label: 'Description' },
    { key: 'unblock', label: 'Unblock', format: (row) => (row.unblock ? 'yes' : 'no') },
    { key: 'interactive', label: 'Interactive', format: (row) => (row.interactive ? 'yes' : 'no') },
    { key: 'implement', label: 'Implements', format: (row) => (row.implement ? 'yes' : 'no') },
    { key: 'review', label: 'Reviews', format: (row) => (row.review ? 'yes' : 'no') },
    { key: 'test', label: 'Tests', format: (row) => (row.test ? 'yes' : 'no') },
    { key: 'merge', label: 'Merges', format: (row) => (row.merge ? 'yes' : 'no') },
    { key: 'deploy', label: 'Deploys', format: (row) => (row.deploy ? 'yes' : 'no') },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'description', label: 'Description', type: 'textarea' },
    { key: 'unblock', label: 'Handles blocked items', type: 'checkbox', hint: 'Unblocks impediments' },
    { key: 'interactive', label: 'Acts on new comments', type: 'checkbox', hint: 'Interactive mode' },
    { key: 'implement', label: 'Writes code', type: 'checkbox', hint: 'May edit the repository; the runner publishes the branch and PR' },
    { key: 'review', label: 'Reviews changes', type: 'checkbox', hint: 'May inspect the diff and approve/request changes' },
    { key: 'test', label: 'Tests deliverables', type: 'checkbox', hint: 'May run tests on the branch HEAD and report pass/fail' },
    { key: 'merge', label: 'Merges pull requests', type: 'checkbox', hint: 'May merge after the QA gate' },
    { key: 'deploy', label: 'Deploys', type: 'checkbox', hint: 'May trigger a deployment after merge' },
  ],
  defaults: () => ({
    name: '',
    description: '',
    unblock: false,
    interactive: false,
    implement: false,
    review: false,
    test: false,
    merge: false,
    deploy: false,
  }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
