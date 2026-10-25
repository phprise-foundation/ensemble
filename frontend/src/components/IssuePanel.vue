<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'
import Avatar from './Avatar.vue'
import FieldLimit from './FieldLimit.vue'
import IssueHistory from './IssueHistory.vue'
import { formatCommentTimestamp, resolveAuthor } from '../lib/comment-author.js'
import { session } from '../store/session.js'
import {
  COMMENT_LIMIT,
  DESCRIPTION_LIMIT,
  countCharacters,
  limitError,
  limitExceeded,
} from '../../shared/limits.js'

const issue = boardStore.selectedIssue
const summaryInput = ref(null)
const comment = ref('')
const activeTab = ref('comments')
const commentsTab = ref(null)
const historyTab = ref(null)

const TABS = [
  { id: 'comments', ref: commentsTab },
  { id: 'history', ref: historyTab },
]

const editingSummary = ref(false)
const summaryDraft = ref('')
const summaryError = ref('')
const editingDescription = ref(false)
const descriptionDraft = ref('')
const descriptionError = ref('')
const descriptionLength = computed(() => countCharacters(descriptionDraft.value))
const descriptionOver = computed(() => limitExceeded(descriptionLength.value, DESCRIPTION_LIMIT))

const commentPayload = computed(() => comment.value.trim())
const commentLength = computed(() => countCharacters(commentPayload.value))
const commentOver = computed(() => limitExceeded(commentLength.value, COMMENT_LIMIT))
const commentError = ref('')

const commentRows = computed(() =>
  issue.value.comments.map((entry) => ({
    id: entry.id,
    author: resolveAuthor({
      agents: boardStore.state.agents,
      user: {
        login: session.login,
        displayName: session.displayName,
        avatar: session.avatar,
        avatarColor: session.avatarColor,
      },
      author: entry.author,
    }),
    at: formatCommentTimestamp(entry.at),
    body: entry.body,
  })),
)

const status = computed(() => (issue.value ? boardStore.status(issue.value.statusId) : null))
const agent = computed(() => (issue.value ? boardStore.agent(issue.value.assigneeId) : null))
const role = computed(() => (agent.value ? agent.value.roleIds.map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') : ''))
const targets = computed(() => (issue.value ? boardStore.userTargets(issue.value.statusId) : []))
const overdue = computed(() => (issue.value ? boardStore.isOverdue(issue.value) : false))

watch(() => issue.value?.id, () => {
  activeTab.value = 'comments'
  editingSummary.value = false
  editingDescription.value = false
  descriptionDraft.value = ''
  descriptionError.value = ''
})
watch(descriptionDraft, () => { descriptionError.value = '' })
watch(comment, () => { commentError.value = '' })

function selectTab(tabId) {
  activeTab.value = tabId
  const target = TABS.find((tab) => tab.id === tabId)?.ref
  nextTick(() => target?.value?.focus())
}

function onTabKeydown(event) {
  if (event.key === 'ArrowRight' || event.key === 'ArrowLeft' || event.key === 'Home' || event.key === 'End') {
    event.preventDefault()
  }
  const offset = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
  if (offset !== 0) {
    const current = TABS.findIndex((tab) => tab.id === activeTab.value)
    const next = (current + offset + TABS.length) % TABS.length
    selectTab(TABS[next].id)
    return
  }
  if (event.key === 'Home') {
    selectTab(TABS[0].id)
    return
  }
  if (event.key === 'End') {
    selectTab(TABS[TABS.length - 1].id)
    return
  }
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    event.target.click()
  }
}

async function changeStatus(event) {
  const statusId = event.target.value
  await boardStore.moveIssue(issue.value.id, { columnId: boardStore.columnOf(statusId), statusId })
}

async function changeAssignee(event) {
  await boardStore.updateIssue(issue.value.id, { assigneeId: event.target.value || null })
}

function startSummaryEdit() {
  summaryDraft.value = issue.value.summary ?? ''
  summaryError.value = ''
  editingSummary.value = true
  nextTick(() => summaryInput.value?.focus())
}

function cancelSummaryEdit() {
  editingSummary.value = false
  summaryDraft.value = ''
  summaryError.value = ''
}

async function saveSummary() {
  if (!summaryDraft.value.trim()) {
    summaryError.value = 'The summary is required.'
    return
  }
  try {
    await boardStore.updateIssue(issue.value.id, { summary: summaryDraft.value.trim() })
  } catch (failure) {
    summaryError.value = failure.message
    return
  }
  editingSummary.value = false
  summaryDraft.value = ''
  summaryError.value = ''
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

    <div class="panel-title-row" v-if="!editingSummary">
      <h2 class="panel-title">{{ issue.summary }}</h2>
      <button class="icon-btn" title="Edit summary" aria-label="Edit summary" @click="startSummaryEdit"><AppIcon name="edit" size="14" /></button>
    </div>
    <form v-else class="panel-summary-edit" @submit.prevent="saveSummary">
      <input
        ref="summaryInput"
        v-model="summaryDraft"
        class="input"
        aria-label="Summary"
        aria-describedby="issue-summary-error"
        :aria-invalid="summaryError ? 'true' : undefined"
      />
      <p id="issue-summary-error" class="error" v-if="summaryError" role="alert">{{ summaryError }}</p>
      <div class="panel-summary-actions">
        <button type="button" class="button ghost" @click="cancelSummaryEdit">Cancel</button>
        <button type="submit" class="button">Save</button>
      </div>
    </form>

    <div class="panel-meta">
      <Avatar
        size="md"
        :name="agent ? agent.name : '?'"
        :image="agent?.portrait"
        :color="agent?.avatarColor || 'var(--surface-3)'"
        decorative
      />
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
      <select :value="issue.assigneeId || ''" @change="changeAssignee">
        <option value="">Unassigned</option>
        <option v-for="member in boardStore.state.agents" :key="member.id" :value="member.id">{{ member.name }}</option>
      </select>
    </label>

    <div v-if="overdue" class="panel-flag">
      <AppIcon name="warning" size="16" /> More than 24h without activity
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

    <section class="panel-section panel-tabs">
      <div class="tabs" role="tablist" aria-label="Issue details">
        <button
          id="issue-tab-comments"
          ref="commentsTab"
          type="button"
          class="tab"
          role="tab"
          :aria-selected="activeTab === 'comments'"
          aria-controls="issue-panel-comments"
          :tabindex="activeTab === 'comments' ? 0 : -1"
          @click="activeTab = 'comments'"
          @keydown="onTabKeydown"
        >
          <AppIcon name="comment" size="16" />
          Comments ({{ issue.comments.length }})
        </button>
        <button
          id="issue-tab-history"
          ref="historyTab"
          type="button"
          class="tab"
          role="tab"
          :aria-selected="activeTab === 'history'"
          aria-controls="issue-panel-history"
          :tabindex="activeTab === 'history' ? 0 : -1"
          @click="activeTab = 'history'"
          @keydown="onTabKeydown"
        >
          History
          <span v-if="boardStore.simulated('history')" class="badge sim">
            <span class="sim-dot sm"></span> Prototype
          </span>
        </button>
      </div>

      <div
        id="issue-panel-comments"
        role="tabpanel"
        aria-labelledby="issue-tab-comments"
        v-show="activeTab === 'comments'"
      >
        <div class="comments-body">
          <div class="comment" v-for="row in commentRows" :key="row.id">
            <div class="comment-header">
              <Avatar
                class="comment-avatar"
                size="sm"
                :name="row.author.name"
                :image="row.author.image ?? null"
                :color="row.author.color"
                decorative
              />
              <div class="comment-author-info">
                <span class="comment-author">{{ row.author.name }}</span>
                <span class="muted small">{{ row.at }}</span>
              </div>
            </div>
            <p>{{ row.body }}</p>
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
        </div>
      </div>

      <div
        id="issue-panel-history"
        role="tabpanel"
        aria-labelledby="issue-tab-history"
        tabindex="0"
        v-show="activeTab === 'history'"
      >
        <IssueHistory :events="issue.history || []" />
      </div>
    </section>
  </aside>
</template>
