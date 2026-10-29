<script setup>
import AppIcon from './AppIcon.vue'

const props = defineProps({
  title: { type: String, default: '' },
  fields: { type: Array, default: () => [] },
  draft: { type: Object, required: true },
  error: { type: String, default: '' },
  extraLabel: { type: String, default: '' },
  extraBusy: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'close', 'extra'])

function optionsFor(field) {
  return typeof field.options === 'function' ? field.options() : field.options || []
}

function isChecked(field, value) {
  return (props.draft[field.key] || []).includes(value)
}

function toggle(field, value) {
  const current = props.draft[field.key] || []
  props.draft[field.key] = current.includes(value) ? current.filter((item) => item !== value) : [...current, value]
}
</script>

<template>
  <div class="modal" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>{{ title }}</h3>
        <button class="icon-btn" title="Close" aria-label="Close" @click="emit('close')"><AppIcon name="close" size="20" /></button>
      </header>

      <div class="modal-body">
        <label class="field" v-for="field in fields" :key="field.key">
          <span>{{ field.label }}</span>
          <input v-if="field.type === 'number'" type="number" v-model.number="draft[field.key]" />
          <input v-else-if="field.type === 'color'" type="color" v-model="draft[field.key]" />
          <textarea v-else-if="field.type === 'textarea'" rows="3" v-model="draft[field.key]"></textarea>
          <select v-else-if="field.type === 'select'" v-model="draft[field.key]">
            <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <div v-else-if="field.type === 'multiselect'" class="checks">
            <label v-for="option in optionsFor(field)" :key="option.value" class="inline">
              <input type="checkbox" :checked="isChecked(field, option.value)" @change="toggle(field, option.value)" />
              {{ option.label }}
            </label>
          </div>
          <input v-else type="text" v-model="draft[field.key]" />
          <small v-if="field.hint" class="muted">{{ field.hint }}</small>
        </label>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <footer class="modal-footer">
        <button v-if="extraLabel" class="button ghost" :disabled="extraBusy" @click="emit('extra')">
          {{ extraLabel }}
        </button>
        <span v-if="extraLabel" class="spacer"></span>
        <button class="button ghost" @click="emit('close')">Cancel</button>
        <button class="button" @click="emit('save')"><AppIcon name="check" size="16" /> Save</button>
      </footer>
    </div>
  </div>
</template>
