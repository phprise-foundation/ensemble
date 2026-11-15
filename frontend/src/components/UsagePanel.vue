<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { request } from '../api/client.js'
import { session } from '../store/session.js'

const data = ref(null)
const error = ref('')
let timer = null

const scoped = (path) => `/api/workspaces/${session.workspaceId}${path}`
const usd = (micros) => `$${(Number(micros || 0) / 1_000_000).toFixed(4)}`
const tokens = (usage) => {
  if (!usage) return 0
  return (usage.inputTokens || 0) + (usage.outputTokens || 0)
    + (usage.reasoningTokens || 0) + (usage.cacheReadTokens || 0) + (usage.cacheWriteTokens || 0)
}

async function load() {
  if (!session.workspaceId) return
  try {
    data.value = await request('GET', scoped('/usage'))
    error.value = ''
  } catch (failure) {
    error.value = failure.message
  }
}

onMounted(() => {
  void load()
  timer = setInterval(load, 30000)
})

onUnmounted(() => clearInterval(timer))

watch(() => session.workspaceId, () => { void load() })
</script>

<template>
  <section id="app-copilot" class="copilot-panel usage-panel">
    <div class="copilot-header">
      <h2>Usage</h2>
    </div>
    <div class="copilot-body">
      <p v-if="error" class="error small">{{ error }}</p>
      <template v-else-if="data">
        <div class="usage-balance">
          <span class="muted small">Balance</span>
          <strong v-if="data.creditMicros === null">Unlimited</strong>
          <strong v-else>{{ usd(data.remainingMicros) }} <span class="muted small">of {{ usd(data.creditMicros) }}</span></strong>
        </div>
        <dl class="usage-grid">
          <dt>Today</dt><dd>{{ usd(data.today.chargedMicros) }}</dd>
          <dt>This month</dt><dd>{{ usd(data.month.chargedMicros) }}</dd>
          <dt>Total charged</dt><dd>{{ usd(data.chargedMicros) }}</dd>
          <dt>Tokens</dt><dd>{{ tokens(data.all) }}</dd>
          <dt>Requests</dt><dd>{{ data.all.requests }}</dd>
        </dl>
        <p v-if="data.remainingMicros === 0" class="error small">Balance exhausted — agents paused.</p>
      </template>
      <p v-else class="muted small">Loading usage…</p>
    </div>
  </section>
</template>
