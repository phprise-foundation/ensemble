<script setup>
import { onMounted, ref } from 'vue'
import { request } from '../api/client.js'
import AppIcon from '../components/AppIcon.vue'

const models = ref([])
const defaultModel = ref('')
const error = ref('')

const usd = (value) => `$${Number(value || 0).toFixed(2)}`

onMounted(async () => {
  try {
    const payload = await request('GET', '/api/models')
    models.value = payload.models || []
    defaultModel.value = payload.default || ''
  } catch (failure) {
    error.value = failure.message
  }
})
</script>

<template>
  <div class="crud">
    <header class="crud-header">
      <h2>Models</h2>
      <span class="spacer"></span>
      <span class="muted small">Read-only catalog</span>
    </header>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-else class="crud-cards">
      <article v-for="model in models" :key="model.id" class="crud-card">
        <header class="crud-card-head">
          <h3 class="crud-card-title">{{ model.name }}</h3>
          <span v-if="model.id === defaultModel" class="chip">Default</span>
        </header>
        <p class="muted small">{{ model.id }}</p>
        <dl class="crud-card-meta">
          <dt>Cached input / 1M</dt><dd>{{ usd(model.pricePerMillion.cachedInputTokens) }}</dd>
          <dt>Input / 1M</dt><dd>{{ usd(model.pricePerMillion.inputTokens) }}</dd>
          <dt>Output / 1M</dt><dd>{{ usd(model.pricePerMillion.outputTokens) }}</dd>
        </dl>
        <p class="muted small"><AppIcon name="roles" size="14" /> Prices are charged by Ensemble; provider details are internal.</p>
      </article>
    </div>
  </div>
</template>
