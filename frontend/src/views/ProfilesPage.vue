<script setup>
import { useRoute } from 'vue-router'
import CrudPage from '../components/CrudPage.vue'
import { boardStore } from '../store/board.js'

const route = useRoute()

const schema = {
  collection: 'profiles',
  singular: 'profile',
  title: 'Profiles',
  feature: 'profiles',
  items: () => boardStore.profilesOf(route.params.teamId),
  inject: () => ({ teamId: route.params.teamId }),
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'style', label: 'Style' },
    { key: 'tone', label: 'Tone' },
    { key: 'emojiUsage', label: 'Emoji' },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'style', label: 'Style description', type: 'textarea' },
    { key: 'tone', label: 'Tone of voice', type: 'text' },
    {
      key: 'emojiUsage',
      label: 'Emoji usage',
      type: 'select',
      options: () => [
        { value: 'Never', label: 'Never' },
        { value: 'Moderate', label: 'Moderate' },
        { value: 'Frequent', label: 'Frequent' },
      ],
    },
  ],
  defaults: () => ({ name: '', style: '', tone: '', emojiUsage: 'Never' }),
}
</script>

<template>
  <CrudPage :schema="schema" />
</template>
