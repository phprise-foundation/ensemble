<script setup>
import { useRoute } from 'vue-router'
import { isSimulated } from '../api/index.js'

const route = useRoute()
</script>

<template>
  <div class="placeholder">
    <h2>{{ route.meta.title }}</h2>
    <p class="muted">
      This screen belongs to the UX map. It becomes functional when the matching
      feature is implemented in the backend.
    </p>
    <p>
      <span v-if="isSimulated(route.meta.feature)" class="badge sim">
        <span class="sim-dot sm"></span> Simulated
      </span>
    </p>
  </div>
</template>
