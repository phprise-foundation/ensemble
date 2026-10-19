<script setup>
import { computed } from 'vue'
import { countCharacters, limitError, limitExceeded, nearLimit } from '../../shared/limits.js'

const props = defineProps({
  id: { type: String, required: true },
  text: { type: String, default: '' },
  limit: { type: Number, required: true },
  field: { type: String, required: true },
  error: { type: String, default: '' },
})

const length = computed(() => countCharacters(props.text))
const over = computed(() => limitExceeded(length.value, props.limit))
const warning = computed(() => nearLimit(length.value, props.limit))
const message = computed(() => props.error || (over.value ? limitError(props.field, length.value, props.limit) : ''))
</script>

<template>
  <div :id="id" class="field-limit" :class="{ warning, over }">
    <span class="field-limit-count">{{ length }}/{{ limit }}</span>
    <span v-if="warning" class="field-limit-warning">Approaching the limit</span>
    <span class="field-limit-error" role="status">{{ message }}</span>
  </div>
</template>
