<script setup>
import { computed, ref } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'

const issue = boardStore.selectedIssue
const comment = ref('')

const status = computed(() => (issue.value ? boardStore.status(issue.value.statusId) : null))
const agent = computed(() => (issue.value ? boardStore.agent(issue.value.assigneeId) : null))
const role = computed(() => (agent.value ? agent.value.roleIds.map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') : ''))
const targets = computed(() => (issue.value ? boardStore.userTargets(issue.value.statusId) : []))
const overdue = computed(() => (issue.value ? boardStore.isOverdue(issue.value) : false))

async function changeStatus(event) {
  const statusId = event.target.value
  await boardStore.moveIssue(issue.value.id, { columnId: boardStore.columnOf(statusId), statusId })
}

async function changeAssignee(event) {
  await boardStore.updateIssue(issue.value.id, { assigneeId: event.target.value || null })
}

async function submitComment() {
  if (!comment.value.trim()) {
    return
  }
  await boardStore.addComment(issue.value.id, comment.value.trim())
  comment.value = ''
}

function format(value) {
  return new Date(value).toLocaleString()
}
</script>

<template>
  <aside class="issue-panel" v-if="issue">
    <header class="panel-header">
      <span class="card-id muted">{{ issue.id }}</span>
      <div class="spacer"></div>
      <button class="icon-btn" title="Close" aria-label="Close" @click="boardStore.clearSelection()">
        <AppIcon name="close" size="20" />
      </button>
    </header>

    <h2 class="panel-title">{{ issue.summary }}</h2>

    <div class="panel-meta">
      <div class="portrait" :style="{ background: agent?.avatarColor || 'var(--surface-3)' }">
        {{ agent ? agent.name.charAt(0) : '?' }}
      </div>
      <div>
        <div class="panel-assignee">{{ agent ? agent.name : 'Unassigned' }}</div>
        <div class="muted small">{{ role || 'No role' }}</div>
      </div>
    </div>

    <label class="field">
      <span>Status</span>
      <select :value="issue.statusId" @change="changeStatus">
        <option v-for="option in targets" :key="option.id" :value="option.id">{{ option.name }}</option>
      </select>
    </label>

    <label class="field">
      <span>Assignee</span>
      <select :value="issue.assigneeId" @change="changeAssignee">
        <option :value="null">Unassigned</option>
        <option v-for="member in boardStore.state.agents" :key="member.id" :value="member.id">{{ member.name }}</option>
      </select>
    </label>

    <div v-if="overdue" class="panel-flag">
      <AppIcon name="warning" size="16" /> More than 24h without activity
    </div>

    <section class="panel-section">
      <h3>Description</h3>
      <p class="body">{{ issue.description || 'No description' }}</p>
    </section>

    <section class="panel-section">
      <h3><AppIcon name="comment" size="16" /> Comments ({{ issue.comments.length }})</h3>
      <div class="comment" v-for="entry in issue.comments" :key="entry.id">
        <span class="comment-author">{{ entry.author }}</span>
        <span class="muted small">{{ format(entry.at) }}</span>
        <p>{{ entry.body }}</p>
      </div>
      <p v-if="!issue.comments.length" class="muted small">No comments yet</p>
      <textarea v-model="comment" rows="3" placeholder="Write a comment..."></textarea>
      <button class="button" @click="submitComment">Comment</button>
    </section>
  </aside>
</template>
