<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { boardStore } from '../store/board.js'
import AppIcon from './AppIcon.vue'
import Avatar from './Avatar.vue'
import AssigneePicker from './AssigneePicker.vue'
import FieldLimit from './FieldLimit.vue'
import FieldValueInput from './FieldValueInput.vue'
import IssueHistory from './IssueHistory.vue'
import { formatCommentTimestamp, resolveAuthor } from '../lib/comment-author.js'
import { session } from '../store/session.js'
import { toast } from '../store/toast.js'
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
const commentInput = ref(null)
const mentionOpen = ref(false)
const mentionSearch = ref('')
const mentionIndex = ref(0)
const mentionUsed = ref(false)
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
const fieldsDraft = ref({})
const fieldsSaving = ref(false)
const fieldsError = ref('')

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
const typeFields = computed(
  () => (issue.value ? boardStore.issueType(issue.value.issueTypeId)?.fields ?? [] : []),
)
const fieldsDirty = computed(
  () => Boolean(issue.value) && JSON.stringify(fieldsDraft.value) !== JSON.stringify(issue.value.fields ?? {}),
)
const repositories = computed(() => boardStore.state.sources ?? [])
const repository = computed(
  () => repositories.value.find((candidate) => candidate.id === issue.value?.repositoryId) ?? null,
)
const workdirState = computed(() => issue.value?.workdirState ?? null)
const runs = computed(() =>
  (issue.value?.runs ?? []).slice().sort((left, right) => Date.parse(right.createdAt) - Date.parse(left.createdAt)),
)
const repositoryDraft = ref('')
const attaching = ref(false)
const resolving = ref('')
const requesting = ref(false)
let runPoll = null

watch(
  () => (issue.value?.runs ?? []).some((run) => run.state === 'claimed' || run.state === 'running'),
  (active) => {
    if (runPoll) {
      clearInterval(runPoll)
      runPoll = null
    }
    if (active) {
      runPoll = setInterval(() => {
        void refreshRuns().catch(() => {})
      }, 5000)
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  if (runPoll) {
    clearInterval(runPoll)
    runPoll = null
  }
})
const mentionCandidates = computed(() => {
  const query = mentionSearch.value.toLowerCase()
  const people = [
    ...boardStore.state.agents.map((agent) => ({
      id: agent.id,
      name: agent.name,
      image: agent.portrait ?? null,
      color: agent.avatarColor,
    })),
    {
      id: 'user',
      name: session.displayName || session.login,
      image: session.avatar || null,
      color: session.avatarColor,
    },
  ]
  const seen = new Set()
  return people.filter((person) => {
    const key = person.name.toLowerCase()
    if (!person.name || seen.has(key) || !key.includes(query)) {
      return false
    }
    seen.add(key)
    return true
  })
})

watch(
  () => issue.value?.id,
  () => {
    activeTab.value = 'comments'
    editingSummary.value = false
    editingDescription.value = false
    descriptionDraft.value = ''
    descriptionError.value = ''
    fieldsDraft.value = JSON.parse(JSON.stringify(issue.value?.fields ?? {}))
    fieldsError.value = ''
    mentionOpen.value = false
    mentionUsed.value = false
    mentionSearch.value = ''
    repositoryDraft.value = ''
    resolving.value = ''
  },
  { immediate: true },
)
watch(descriptionDraft, () => { descriptionError.value = '' })
watch(comment, () => { commentError.value = '' })

function agentName(id) {
  return boardStore.agent(id)?.name ?? id
}

function formatRunTime(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

async function attachRepository() {
  if (!repositoryDraft.value) return
  attaching.value = true
  try {
    await boardStore.attachRepository(issue.value.id, repositoryDraft.value)
    toast.success('Repository attached.')
    repositoryDraft.value = ''
  } catch (failure) {
    toast.error(failure?.message ?? 'Could not attach the repository.')
  } finally {
    attaching.value = false
  }
}

async function resolveWorkdir(strategy) {
  resolving.value = strategy
  try {
    await boardStore.resolveWorkdir(issue.value.id, strategy)
    toast.success('Workdir resolved.')
  } catch (failure) {
    toast.error(failure?.message ?? 'Could not resolve the workdir.')
  } finally {
    resolving.value = ''
  }
}

async function refreshRuns() {
  if (issue.value) {
    await boardStore.refreshIssue(issue.value.id)
  }
}

async function unblockIssue() {
  try {
    await boardStore.updateIssue(issue.value.id, { blocked: false })
    toast.success('Issue unblocked.')
  } catch (failure) {
    toast.error(failure?.message ?? 'Could not unblock the issue.')
  }
}

async function requestRun() {
  if (!issue.value?.assigneeId) {
    toast.error('Assign an agent before running this issue.')
    return
  }
  requesting.value = true
  try {
    await boardStore.requestRun(issue.value.id)
    toast.success('Run requested.')
  } catch (failure) {
    toast.error(failure?.message ?? 'Could not request a run.')
  } finally {
    requesting.value = false
  }
}

async function saveFields() {
  if (!fieldsDirty.value || fieldsSaving.value) return
  fieldsSaving.value = true
  fieldsError.value = ''
  try {
    await boardStore.updateIssue(issue.value.id, { fields: fieldsDraft.value })
    toast.success('Fields saved.')
  } catch (failure) {
    fieldsError.value = failure?.message ?? 'Could not save the fields.'
  } finally {
    fieldsSaving.value = false
  }
}

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

async function changeAssignee(assigneeId) {
  await boardStore.updateIssue(issue.value.id, { assigneeId: assigneeId || null })
}

function onCommentInput(event) {
  const value = event.target.value
  const caret = event.target.selectionStart ?? value.length
  const before = value.slice(0, caret)
  const match = before.match(/(?:^|\s)@([^\s@]*)$/)
  if (!match || mentionUsed.value) {
    mentionOpen.value = false
    return
  }
  mentionSearch.value = match[1]
  mentionIndex.value = 0
  mentionOpen.value = true
}

function onCommentKeydown(event) {
  if (!mentionOpen.value) {
    return
  }
  if (event.key === 'Escape') {
    mentionOpen.value = false
    return
  }
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    if (!mentionCandidates.value.length) {
      return
    }
    event.preventDefault()
    const offset = event.key === 'ArrowDown' ? 1 : -1
    mentionIndex.value =
      (mentionIndex.value + offset + mentionCandidates.value.length) % mentionCandidates.value.length
    return
  }
  if (event.key === 'Enter' && mentionCandidates.value.length) {
    event.preventDefault()
    chooseMention(mentionCandidates.value[mentionIndex.value] ?? mentionCandidates.value[0])
  }
}

function chooseMention(person) {
  const input = commentInput.value
  const caret = input?.selectionStart ?? comment.value.length
  const before = comment.value.slice(0, caret).replace(/(?:^|\s)@[^\s@]*$/, (token) => {
    const prefix = token.startsWith('@') ? '' : token.charAt(0)
    return `${prefix}@${person.name} `
  })
  comment.value = before + comment.value.slice(caret)
  mentionOpen.value = false
  mentionUsed.value = true
  nextTick(() => {
    input?.focus()
    input?.setSelectionRange(before.length, before.length)
  })
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
  mentionOpen.value = false
  mentionUsed.value = false
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
      <h2
        class="panel-title editable"
        role="button"
        tabindex="0"
        title="Click to edit"
        aria-label="Edit summary"
        @click="startSummaryEdit"
        @keydown.enter.prevent="startSummaryEdit"
        @keydown.space.prevent="startSummaryEdit"
      >{{ issue.summary }}</h2>
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

    <div v-if="issue.blocked" class="panel-blocked">
      <AppIcon name="warning" size="16" />
      <div>
        <strong>Blocked</strong>
        <p class="muted small">Check the latest run/comment; unblock when the impediment is resolved.</p>
      </div>
      <button class="button ghost" @click="unblockIssue">Unblock</button>
    </div>

    <div class="panel-meta">
      <AssigneePicker
        :assignee="agent"
        :agents="boardStore.state.agents"
        @select="changeAssignee"
      />
      <span class="muted small">{{ role || 'No role' }}</span>
    </div>

    <label class="field">
      <span>Status</span>
      <select :value="issue.statusId" @change="changeStatus">
        <option v-for="option in targets" :key="option.id" :value="option.id">{{ option.name }}</option>
      </select>
    </label>

    <section class="panel-section panel-repository">
      <div class="section-heading"><h3>Repository</h3></div>
      <template v-if="issue.repositoryId">
        <p class="body">{{ repository?.name ?? issue.repositoryId }}</p>
        <p class="muted small">Workdir: {{ workdirState }}</p>
      </template>
      <template v-else>
        <p class="muted small">
          Scratch workdir: agents do not execute this issue until a repository is attached.
        </p>
        <template v-if="repositories.length">
          <label class="field">
            <span>Attach repository</span>
            <select v-model="repositoryDraft">
              <option value="">Choose a repository</option>
              <option v-for="candidate in repositories" :key="candidate.id" :value="candidate.id">
                {{ candidate.name }}
              </option>
            </select>
          </label>
          <button class="button" :disabled="!repositoryDraft || attaching" @click="attachRepository">
            Attach
          </button>
        </template>
        <p v-else class="muted small">Register a repository in Sources first.</p>
      </template>
      <div v-if="workdirState === 'conflict'" class="conflict-box">
        <p class="error small">The workdir is in conflict. Choose how to resolve it:</p>
        <div class="conflict-actions">
          <button class="button" :disabled="Boolean(resolving)" @click="resolveWorkdir('remote_wins')">
            Remote wins
          </button>
          <button class="button" :disabled="Boolean(resolving)" @click="resolveWorkdir('local_wins')">
            Local wins
          </button>
          <button class="button" :disabled="Boolean(resolving)" @click="resolveWorkdir('merge')">
            Merge
          </button>
        </div>
      </div>
    </section>

    <div v-if="overdue" class="panel-flag">
      <AppIcon name="warning" size="16" /> More than 24h without activity
    </div>

    <section class="panel-section">
      <div class="section-heading">
        <h3>Description</h3>
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

      <p
        v-else
        class="body editable"
        role="button"
        tabindex="0"
        title="Click to edit"
        aria-label="Edit description"
        @click="startDescriptionEdit"
        @keydown.enter.prevent="startDescriptionEdit"
        @keydown.space.prevent="startDescriptionEdit"
      >{{ issue.description || 'No description' }}</p>
    </section>

    <section v-if="typeFields.length" class="panel-section panel-fields">
      <div class="section-heading">
        <h3>Fields</h3>
      </div>
      <FieldValueInput
        v-for="field in typeFields"
        :key="field.id"
        v-model="fieldsDraft[field.key]"
        :field="field"
      />
      <p v-if="fieldsError" class="error small" role="alert">{{ fieldsError }}</p>
      <div class="section-actions">
        <button type="button" class="button" :disabled="!fieldsDirty || fieldsSaving" @click="saveFields">
          Save fields
        </button>
      </div>
    </section>

    <section class="panel-section panel-runs">
      <div class="section-heading">
        <h3>Runs</h3>
        <div class="section-actions-inline">
          <button
            type="button"
            class="button ghost"
            :disabled="issue.blocked || !issue.assigneeId || requesting"
            @click="requestRun"
          >
            Run now
          </button>
          <button type="button" class="link-button" @click="refreshRuns">Refresh</button>
        </div>
      </div>
      <p v-if="!issue.assigneeId" class="muted small">Assign an agent to run this issue.</p>
      <p v-else-if="!runs.length" class="muted small">No runs yet.</p>
      <ol v-else class="run-list">
        <li v-for="run in runs" :key="run.id" class="run-entry" :data-run-state="run.state">
          <div class="run-head">
            <span class="run-state" :class="'run-' + run.state">{{ run.state }}</span>
            <span class="muted small">{{ agentName(run.agentId) }}</span>
            <span class="spacer"></span>
            <a
              v-if="run.pullRequestUrl"
              class="run-pr"
              :href="run.pullRequestUrl"
              target="_blank"
              rel="noreferrer"
            >
              Pull request
            </a>
            <button
              v-if="run.state === 'failed'"
              type="button"
              class="link-button"
              @click="requestRun"
            >
              Retry
            </button>
            <time class="muted small" :datetime="run.createdAt">
              {{ formatRunTime(run.finishedAt || run.startedAt || run.createdAt) }}
            </time>
          </div>
          <p v-if="run.reason" class="small">{{ run.reason }}</p>
          <details v-if="run.message" class="run-output">
            <summary>Output</summary>
            <pre>{{ run.message }}</pre>
          </details>
        </li>
      </ol>
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
          <div class="comment-editor">
            <textarea
              ref="commentInput"
              v-model="comment"
              rows="3"
              placeholder="Write a comment... use @ to mention"
              aria-label="Comment"
              aria-describedby="issue-comment-limit"
              :aria-invalid="commentOver || Boolean(commentError)"
              @input="onCommentInput"
              @keydown="onCommentKeydown"
            ></textarea>
            <ul
              v-if="mentionOpen && mentionCandidates.length"
              class="mention-menu"
              role="listbox"
              aria-label="Mention"
            >
              <li v-for="(person, index) in mentionCandidates" :key="person.id">
                <button
                  type="button"
                  role="option"
                  :aria-selected="index === mentionIndex"
                  :class="{ active: index === mentionIndex }"
                  @mousedown.prevent="chooseMention(person)"
                >
                  <Avatar
                    size="sm"
                    :name="person.name"
                    :image="person.image"
                    :color="person.color"
                    decorative
                  />
                  <span>{{ person.name }}</span>
                </button>
              </li>
            </ul>
            <p v-if="mentionUsed" class="muted small">One mention per comment.</p>
          </div>
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
