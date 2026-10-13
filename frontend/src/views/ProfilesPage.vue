<script setup>
import CrudPage from '../components/CrudPage.vue'

const schema = {
  collection: 'profiles',
  singular: 'profile',
  title: 'Profiles',
  feature: 'profiles',
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
