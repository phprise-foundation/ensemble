<script setup>
import { computed, ref, watch } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'
import FieldLimit from './FieldLimit.vue'
import {
  COMMENT_LIMIT,
  DESCRIPTION_LIMIT,
  countCharacters,
  limitError,
  limitExceeded,
} from '../../shared/limits.js'

const issue = boardStore.selectedIssue
const comment = ref('')

const editingDescription = ref(false)
const descriptionDraft = ref('')
const descriptionError = ref('')
const descriptionLength = computed(() => countCharacters(descriptionDraft.value))
const descriptionOver = computed(() => limitExceeded(descriptionLength.value, DESCRIPTION_LIMIT))

const commentPayload = computed(() => comment.value.trim())
const commentLength = computed(() => countCharacters(commentPayload.value))
const commentOver = computed(() => limitExceeded(commentLength.value, COMMENT_LIMIT))
const commentError = ref('')

const status = computed(() => (issue.value ? boardStore.status(issue.value.statusId) : null))
const agent = computed(() => (issue.value ? boardStore.agent(issue.value.assigneeId) : null))
const role = computed(() => (agent.value ? agent.value.roleIds.map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') : ''))
const targets = computed(() => (issue.value ? boardStore.userTargets(issue.value.statusId) : []))
const overdue = computed(() => (issue.value ? boardStore.isOverdue(issue.value) : false))

watch(() => issue.value?.id, () => {
  editingDescription.value = false
  descriptionDraft.value = ''
  descriptionError.value = ''
})
watch(descriptionDraft, () => { descriptionError.value = '' })
watch(comment, () => { commentError.value = '' })

async function changeStatus(event) {
  const statusId = event.target.value
  await boardStore.moveIssue(issue.value.id, { columnId: boardStore.columnOf(statusId), statusId })
}

async function changeAssignee(event) {
  await boardStore.updateIssue(issue.value.id, { assigneeId: event.target.value || null })
}

function startDescriptionEdit() {
  descriptionDraft.value = issue.value.description ?? ''
  descriptionError.value = ''
  editingDescription.value = true
}

function cancelDescriptionEdit() {
  editingDescription.value = false
  descriptionDraft.value = ''
  descriptionError.value = ''
}

async function saveDescription() {
  if (descriptionOver.value) {
    descriptionError.value = limitError('description', descriptionLength.value, DESCRIPTION_LIMIT)
    return
  }
  try {
    await boardStore.updateIssue(issue.value.id, { description: descriptionDraft.value })
  } catch (failure) {
    descriptionError.value = failure.message
    return
  }
  editingDescription.value = false
  descriptionDraft.value = ''
  descriptionError.value = ''
}

async function submitComment() {
  if (!commentPayload.value) {
    return
  }
  if (commentOver.value) {
    commentError.value = limitError('comment', commentLength.value, COMMENT_LIMIT)
    return
  }
  try {
    await boardStore.addComment(issue.value.id, commentPayload.value)
  } catch (failure) {
    commentError.value = failure.message
    return
  }
  comment.value = ''
  commentError.value = ''
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
      <button class="icon-btn" title="Close" @click="boardStore.clearSelection()">
        <AppIcon name="close" />
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
      <AppIcon name="warning" size="15" /> More than 24h without activity
    </div>

    <section class="panel-section">
      <div class="section-heading">
        <h3>Description</h3>
        <button
          v-if="!editingDescription"
          type="button"
          class="link-button"
          @click="startDescriptionEdit"
        >
          Edit
        </button>
      </div>

      <template v-if="editingDescription">
        <textarea
          v-model="descriptionDraft"
          rows="4"
          aria-label="Description"
          aria-describedby="issue-description-limit"
          :aria-invalid="descriptionOver || Boolean(descriptionError)"
        ></textarea>
        <FieldLimit
          id="issue-description-limit"
          field="description"
          :text="descriptionDraft"
          :limit="DESCRIPTION_LIMIT"
          :error="descriptionError"
        />
        <div class="section-actions">
          <button type="button" class="button ghost" @click="cancelDescriptionEdit">Cancel</button>
          <button type="button" class="button" :disabled="descriptionOver" @click="saveDescription">Save</button>
        </div>
      </template>

      <p v-else class="body">{{ issue.description || 'No description' }}</p>
    </section>

    <section class="panel-section">
      <h3><AppIcon name="comment" size="15" /> Comments ({{ issue.comments.length }})</h3>
      <div class="comment" v-for="entry in issue.comments" :key="entry.id">
        <span class="comment-author">{{ entry.author }}</span>
        <span class="muted small">{{ format(entry.at) }}</span>
        <p>{{ entry.body }}</p>
      </div>
      <p v-if="!issue.comments.length" class="muted small">No comments yet</p>
      <textarea
        v-model="comment"
        rows="3"
        placeholder="Write a comment..."
        aria-label="Comment"
        aria-describedby="issue-comment-limit"
        :aria-invalid="commentOver || Boolean(commentError)"
      ></textarea>
      <FieldLimit
        id="issue-comment-limit"
        field="comment"
        :text="commentPayload"
        :limit="COMMENT_LIMIT"
        :error="commentError"
      />
      <button class="button" :disabled="commentOver" @click="submitComment">Comment</button>
    </section>
  </aside>
</template>
