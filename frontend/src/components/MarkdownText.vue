<script setup>
import { computed } from 'vue'
import { renderMarkdown } from '../lib/markdown.js'

const props = defineProps({
  text: { type: String, default: '' },
  tag: { type: String, default: 'div' },
})

const html = computed(() => renderMarkdown(props.text))
</script>

<template>
  <component :is="tag" class="markdown" v-html="html"></component>
</template>
