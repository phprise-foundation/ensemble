<script setup>
import { computed } from 'vue'
import { boardStore } from '../store/board.js'
import Avatar from './Avatar.vue'

const agents = computed(() => {
  const activity = boardStore.state.activityAgents ?? []
  if (activity.length) {
    return activity
  }
  return boardStore.state.agents.map((agent) => ({
    id: agent.id,
    name: agent.name,
    active: agent.active,
    roleIds: agent.roleIds ?? [],
    language: agent.language ?? 'pt-BR',
  }))
})

const runs = computed(() => boardStore.state.activityRuns ?? [])

const running = computed(() =>
  runs.value.map((run) => ({
    ...run,
    agent: agents.value.find((agent) => agent.id === run.agentId) ?? null,
    statusName: boardStore.status(run.statusId)?.name ?? '—',
  })),
)

const runningIds = computed(() => new Set(runs.value.map((run) => run.agentId)))
const sleeping = computed(() => agents.value.filter((agent) => agent.active && !runningIds.value.has(agent.id)))
const inactive = computed(() => agents.value.filter((agent) => !agent.active))

function rolesOf(agent) {
  return (agent.roleIds ?? []).map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') || 'No role'
}

function imageOf(agent) {
  return boardStore.agent(agent?.id)?.portrait ?? null
}
</script>

<template>
  <div class="agent-monitor">
    <section class="monitor-group">
      <h3>Running <span class="muted small">{{ running.length }}</span></h3>
      <p v-if="!running.length" class="muted small">No agent is running right now.</p>
      <div v-for="entry in running" :key="entry.id" class="monitor-entry" data-agent-state="running">
        <Avatar size="sm" :name="entry.agent?.name ?? '?'" :image="imageOf(entry.agent)" decorative />
        <div class="monitor-info">
          <strong>{{ entry.agent?.name ?? 'Agent' }}</strong>
          <span class="muted small">
            {{ entry.issueKey ?? 'issue' }} · {{ entry.statusName }}
          </span>
          <span class="monitor-summary">{{ entry.issueSummary ?? 'Working' }}</span>
        </div>
        <span class="monitor-live"><span class="card-running-dot" aria-hidden="true"></span>running</span>
      </div>
    </section>

    <section class="monitor-group">
      <h3>Sleeping <span class="muted small">{{ sleeping.length }}</span></h3>
      <p v-if="!sleeping.length" class="muted small">No agent is waiting.</p>
      <div v-for="agent in sleeping" :key="agent.id" class="monitor-entry" data-agent-state="sleeping">
        <Avatar size="sm" :name="agent.name" :image="imageOf(agent)" decorative />
        <div class="monitor-info">
          <strong>{{ agent.name }}</strong>
          <span class="muted small">{{ rolesOf(agent) }}</span>
        </div>
        <span class="muted small">sleeping</span>
      </div>
    </section>

    <section class="monitor-group">
      <h3>Inactive <span class="muted small">{{ inactive.length }}</span></h3>
      <p v-if="!inactive.length" class="muted small">No inactive agents.</p>
      <div v-for="agent in inactive" :key="agent.id" class="monitor-entry" data-agent-state="inactive">
        <Avatar size="sm" :name="agent.name" :image="imageOf(agent)" decorative />
        <div class="monitor-info">
          <strong>{{ agent.name }}</strong>
          <span class="muted small">{{ rolesOf(agent) }}</span>
        </div>
        <span class="muted small">inactive</span>
      </div>
    </section>
  </div>
</template>
