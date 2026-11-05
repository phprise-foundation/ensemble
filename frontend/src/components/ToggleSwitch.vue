<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

function onChange(event) {
  emit('update:modelValue', event.target.checked)
}
</script>

<template>
  <label class="switch" :class="{ disabled }">
    <input
      type="checkbox"
      role="switch"
      :checked="modelValue"
      :disabled="disabled"
      :aria-label="label || undefined"
      :aria-checked="modelValue"
      @change="onChange"
    />
    <span class="slider round" aria-hidden="true"></span>
  </label>
</template>

<style scoped>
.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex: none;
}

.switch.disabled {
  opacity: 0.55;
}

.switch input {
  position: absolute;
  opacity: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  cursor: pointer;
}

.switch input:disabled {
  cursor: not-allowed;
}

.slider {
  position: absolute;
  inset: 0;
  background: var(--border-strong, #9aa4b2);
  border-radius: 24px;
  transition: background-color 0.2s ease;
  pointer-events: none;
}

.slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  top: 3px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s ease;
}

.switch input:checked + .slider {
  background: var(--accent, #4c9aff);
}

.switch input:checked + .slider::before {
  transform: translateX(20px);
}

.switch input:focus-visible + .slider {
  outline: 2px solid var(--accent, #4c9aff);
  outline-offset: 2px;
}
</style>
