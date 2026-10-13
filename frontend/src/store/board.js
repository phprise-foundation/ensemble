import { computed, reactive } from 'vue'
import { api } from '../api/index.js'
import { isSimulated } from '../api/simulation.js'

const OVERDUE_MS = 24 * 60 * 60 * 1000

const state = reactive({
  loading: false,
  error: '',
  boards: [],
  boardId: 'kanban',
  columns: [],
  statuses: [],
  transitions: [],
  issueTypes: [],
  roles: [],
  profiles: [],
  agents: [],
  experiences: [],
  issues: [],
  selectedIssueId: null,
})

const find = (list, id) => list.find((item) => item.id === id) ?? null

export const boardStore = {
  state,
  status: (id) => find(state.statuses, id),
  column: (id) => find(state.columns, id),
  agent: (id) => find(state.agents, id),
  profile: (id) => find(state.profiles, id),
  role: (id) => find(state.roles, id),
  issueType: (id) => find(state.issueTypes, id),
  selectedIssue: computed(() => find(state.issues, state.selectedIssueId)),

  issuesForColumn(columnId) {
    const column = find(state.columns, columnId)
    if (!column) {
      return []
    }
    return state.issues
      .filter((issue) => column.statusIds.includes(issue.statusId))
      .sort((a, b) => a.order - b.order)
  },

  canUserMove(fromStatusId, toStatusId) {
    if (fromStatusId === toStatusId) {
      return true
    }
    return state.transitions.some(
      (transition) =>
        transition.to === toStatusId &&
        (transition.from === '*' || transition.from === fromStatusId) &&
        transition.type !== 'agent',
    )
  },

  canEnterColumn(fromStatusId, columnId) {
    const column = find(state.columns, columnId)
    if (!column) {
      return false
    }
    return column.statusIds.some((statusId) => boardStore.canUserMove(fromStatusId, statusId))
  },

  userTargets(fromStatusId) {
    return state.statuses.filter(
      (status) => status.id === fromStatusId || boardStore.canUserMove(fromStatusId, status.id),
    )
  },

  columnOf(statusId) {
    const column = state.columns.find((candidate) => candidate.statusIds.includes(statusId))
    return column ? column.id : null
  },

  isOverdue(issue) {
    const status = boardStore.status(issue.statusId)
    if (!status || status.type === 'final') {
      return false
    }
    return Date.now() - Date.parse(issue.updatedAt) > OVERDUE_MS
  },

  simulated: (feature) => isSimulated(feature),

  async load() {
    state.loading = true
    state.error = ''
    try {
      const workspace = await api.fetchWorkspace()
      Object.assign(state, workspace)
      state.issues = await api.fetchIssues()
    } catch (error) {
      state.error = error.message
    } finally {
      state.loading = false
    }
  },

  select(id) {
    state.selectedIssueId = id
  },

  clearSelection() {
    state.selectedIssueId = null
  },

  async moveIssue(issueId, target) {
    const updated = await api.moveIssue(issueId, target)
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    state.issues.splice(index, 1, updated)
    state.issues = await api.fetchIssues()
  },

  async createIssue(payload) {
    const created = await api.createIssue(payload)
    state.issues = await api.fetchIssues()
    return created
  },

  async updateIssue(id, patch) {
    const updated = await api.updateIssue(id, patch)
    const index = state.issues.findIndex((issue) => issue.id === id)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
    }
    return updated
  },

  async saveEntity(collection, data) {
    const saved = data.id
      ? await api.updateEntity(collection, data.id, data)
      : await api.createEntity(collection, data)
    const list = state[collection]
    const index = list.findIndex((item) => item.id === saved.id)
    if (index >= 0) {
      list.splice(index, 1, saved)
    } else {
      list.push(saved)
    }
    return saved
  },

  async removeEntity(collection, id) {
    await api.deleteEntity(collection, id)
    state[collection] = state[collection].filter((item) => item.id !== id)
  },

  async addComment(issueId, body) {
    const updated = await api.addComment(issueId, { author: 'user', body })
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    state.issues.splice(index, 1, updated)
  },
}
