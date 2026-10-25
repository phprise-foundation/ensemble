<script setup>
const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Boolean, Array], default: null },
})

const emit = defineEmits(['update:modelValue'])

const hasOptions = (field) => field.type === 'single_select' || field.type === 'multi_select'

function toggle(option) {
  const selected = Array.isArray(props.modelValue) ? props.modelValue : []
  emit(
    'update:modelValue',
    selected.includes(option) ? selected.filter((item) => item !== option) : [...selected, option],
  )
}

function setNumber(value) {
  emit('update:modelValue', value === '' ? null : Number(value))
}
</script>

<template>
  <label class="field field-value">
    <span>{{ field.label }}<template v-if="field.required"> *</template></span>
    <input
      v-if="field.type === 'date'"
      type="date"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <input
      v-else-if="field.type === 'number'"
      type="number"
      :value="modelValue"
      @input="setNumber($event.target.value)"
    />
    <input
      v-else-if="field.type === 'boolean'"
      type="checkbox"
      :checked="Boolean(modelValue)"
      @change="emit('update:modelValue', $event.target.checked)"
    />
    <textarea
      v-else-if="field.type === 'textarea'"
      rows="3"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    ></textarea>
    <select
      v-else-if="field.type === 'single_select'"
      :value="modelValue"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <option value="">—</option>
      <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
    </select>
    <div v-else-if="hasOptions(field)" class="checks">
      <label v-for="option in field.options || []" :key="option" class="inline">
        <input type="checkbox" :checked="(modelValue || []).includes(option)" @change="toggle(option)" />
        {{ option }}
      </label>
    </div>
    <input
      v-else
      type="text"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <slot />
  </label>
</template>
