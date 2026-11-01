<script setup>
import { computed, ref } from 'vue'
import MarkdownText from './MarkdownText.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  rows: { type: Number, default: 5 },
  id: { type: String, default: undefined },
  placeholder: { type: String, default: '' },
  ariaLabel: { type: String, default: undefined },
  describedBy: { type: String, default: undefined },
  ariaInvalid: { type: [Boolean, String], default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'input', 'keydown', 'blur'])

const mode = ref('write')
const textarea = ref(null)
const count = computed(() => String(props.modelValue ?? '').length)

function onInput(event) {
  emit('update:modelValue', event.target.value)
  emit('input', event)
}

function focus() {
  textarea.value?.focus()
}

defineExpose({ focus, textarea })
</script>

<template>
  <div class="markdown-field">
    <div class="markdown-toolbar">
      <div class="markdown-tabs" role="tablist" aria-label="Markdown editor">
        <button
          type="button"
          class="markdown-tab"
          role="tab"
          :aria-selected="mode === 'write'"
          :class="{ active: mode === 'write' }"
          @click="mode = 'write'"
        >
          Write
        </button>
        <button
          type="button"
          class="markdown-tab"
          role="tab"
          :aria-selected="mode === 'preview'"
          :class="{ active: mode === 'preview' }"
          @click="mode = 'preview'"
        >
          Preview
        </button>
      </div>
      <span class="markdown-hint">{{ count }} · Markdown</span>
    </div>
    <textarea
      v-if="mode === 'write'"
      :id="id"
      ref="textarea"
      class="markdown-input"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :aria-label="ariaLabel"
      :aria-describedby="describedBy"
      :aria-invalid="ariaInvalid"
      :disabled="disabled"
      @input="onInput"
      @keydown="$emit('keydown', $event)"
      @blur="$emit('blur', $event)"
    ></textarea>
    <div v-else class="markdown-preview">
      <MarkdownText v-if="modelValue" :text="modelValue" />
      <p v-else class="muted small">Nothing to preview yet.</p>
    </div>
  </div>
</template>
