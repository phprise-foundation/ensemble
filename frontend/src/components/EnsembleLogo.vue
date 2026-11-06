<script setup>
import { computed } from 'vue'
import blackLogo from '../assets/brand/ens-logo-black.svg'
import bluesmokeLogo from '../assets/brand/ens-logo-bluesmoke.svg'
import whiteLogo from '../assets/brand/ens-logo-white.svg'

const props = defineProps({
  variant: { type: String, default: 'mark' },
  tone: { type: String, default: 'bluesmoke' },
  title: { type: String, default: 'Ensemble' },
})

const SOURCES = {
  black: blackLogo,
  bluesmoke: bluesmokeLogo,
  white: whiteLogo,
}

const source = computed(() => SOURCES[props.tone] ?? SOURCES.bluesmoke)
</script>

<template>
  <img
    class="ensemble-logo"
    :class="`ensemble-logo--${variant}`"
    :src="source"
    :alt="title"
    draggable="false"
  />
</template>
