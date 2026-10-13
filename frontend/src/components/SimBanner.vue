<script setup>
import { hasSimulation } from '../api/index.js'
</script>

<template>
  <div v-if="hasSimulation" class="sim-banner">
    <span class="sim-dot"></span>
    <strong>Prototype backend</strong>
    <span class="muted">— data is saved to a local JSON file (.data/db.json); agents are not triggered yet.</span>
  </div>
</template>
