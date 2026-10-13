<script setup>
import { hasSimulation } from '../api/index.js'
</script>

<template>
  <div v-if="hasSimulation" class="sim-banner">
    <span class="sim-dot"></span>
    <strong>Simulated mode</strong>
    <span class="muted">— screens backed by demo data. Real endpoints are off.</span>
  </div>
</template>
