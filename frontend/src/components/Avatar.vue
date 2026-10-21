<script setup>
import { computed, ref, watch } from 'vue'

const INK_DARK = '#000000'
const INK_LIGHT = '#ffffff'
const INK_THRESHOLD = 0.179

const props = defineProps({
  name: { type: String, required: true },
  image: { type: String, default: '' },
  color: { type: String, default: '#333333' },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  ring: { type: Boolean, default: false },
  decorative: { type: Boolean, default: false },
})

const failed = ref(false)

watch(
  () => props.image,
  () => {
    failed.value = false
  },
)

function channel(value) {
  const normalized = value / 255
  return normalized <= 0.03928
    ? normalized / 12.92
    : ((normalized + 0.055) / 1.055) ** 2.4
}

function readableInk(background) {
  const value = background.replace('#', '')
  if (!/^[0-9a-f]{6}$/i.test(value)) {
    return INK_LIGHT
  }
  const channels = [0, 2, 4].map((offset) =>
    channel(parseInt(value.slice(offset, offset + 2), 16)),
  )
  const luminance =
    0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
  return luminance > INK_THRESHOLD ? INK_DARK : INK_LIGHT
}

const initial = computed(
  () => (props.name.trim().charAt(0) || '?').toUpperCase(),
)

const ink = computed(() => readableInk(props.color))

const style = computed(() => ({
  backgroundColor: props.color,
  color: ink.value,
}))

const label = computed(() => `Avatar de ${props.name}`)
</script>

<template>
  <span
    class="avatar"
    :class="[size, { ring }]"
    :style="style"
    :role="decorative ? undefined : 'img'"
    :aria-label="decorative ? undefined : label"
    :aria-hidden="decorative || undefined"
  >
    <img v-if="image && !failed" :src="image" alt="" @error="failed = true" />
    <template v-else>{{ initial }}</template>
  </span>
</template>
