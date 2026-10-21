import { computed, reactive } from 'vue'
import { api } from '../api/index.js'
import { isSimulated } from '../api/simulation.js'
import { session } from './session.js'

const OVERDUE_MS = 24 * 60 * 60 * 1000
const find = (list, id) => list.find((item) => item.id === id) ?? null

const state = reactive({
  loading: false,
  error: '',
  workspaces: [],
  workflows: [],
  statuses: [],
  transitions: [],
  issueTypes: [],
  boards: [],
  teams: [],
  roles: [],
  profiles: [],
  agents: [],
  experiences: [],
  sources: [],
  issues: [],
  boardId: null,
  selectedIssueId: null,
})

const board = computed(() => find(state.boards, state.boardId))
const boardColumns = computed(() => board.value?.columns ?? [])
const boardStatuses = computed(() => state.statuses.filter((status) => status.workflowId === board.value?.workflowId))
const boardTransitions = computed(() => state.transitions.filter((transition) => transition.workflowId === board.value?.workflowId))
const boardIssueTypes = computed(() => state.issueTypes.filter((type) => type.workflowId === board.value?.workflowId))

export const boardStore = {
  state,
  board,
  boardColumns,
  boardStatuses,
  boardTransitions,
  boardIssueTypes,
  workspace: computed(() => state.workspaces[0] ?? null),
  selectedIssue: computed(() => find(state.issues, state.selectedIssueId)),

  status: (id) => find(state.statuses, id),
  workflow: (id) => find(state.workflows, id),
  team: (id) => find(state.teams, id),
  role: (id) => find(state.roles, id),
  profile: (id) => find(state.profiles, id),
  agent: (id) => find(state.agents, id),
  issueType: (id) => find(state.issueTypes, id),

  statusesOf: (workflowId) => state.statuses.filter((status) => status.workflowId === workflowId),
  transitionsOf: (workflowId) => state.transitions.filter((transition) => transition.workflowId === workflowId),
  issueTypesOf: (workflowId) => state.issueTypes.filter((type) => type.workflowId === workflowId),
  agentsOf: (teamId) => state.agents.filter((agent) => agent.teamId === teamId),
  rolesOf: (teamId) => state.roles.filter((role) => role.teamId === teamId),
  profilesOf: (teamId) => state.profiles.filter((profile) => profile.teamId === teamId),
  boardsOf: (workspaceId) => state.boards.filter((item) => item.workspaceId === workspaceId),
  workflowsOf: (workspaceId) => state.workflows.filter((item) => item.workspaceId === workspaceId),
  teamsOf: (workspaceId) => state.teams.filter((item) => item.workspaceId === workspaceId),
  sourcesOf: (workspaceId) => state.sources.filter((item) => item.workspaceId === workspaceId),

  setBoard(id) {
    state.boardId = id || state.boards[0]?.id || null
  },

  issuesForColumn(columnId) {
    const column = boardColumns.value.find((candidate) => candidate.id === columnId)
    if (!column) {
      return []
    }
    return state.issues
      .filter((issue) => issue.boardId === state.boardId && column.statusIds.includes(issue.statusId))
      .sort((a, b) => a.order - b.order)
  },

  canUserMove(fromStatusId, toStatusId) {
    if (fromStatusId === toStatusId) {
      return true
    }
    return boardTransitions.value.some(
      (transition) =>
        transition.to === toStatusId &&
        (transition.from === '*' || transition.from === fromStatusId) &&
        transition.type !== 'agent',
    )
  },

  canEnterColumn(fromStatusId, columnId) {
    const column = boardColumns.value.find((candidate) => candidate.id === columnId)
    return column ? column.statusIds.some((statusId) => boardStore.canUserMove(fromStatusId, statusId)) : false
  },

  userTargets(fromStatusId) {
    return boardStatuses.value.filter((status) => status.id === fromStatusId || boardStore.canUserMove(fromStatusId, status.id))
  },

  columnOf(statusId) {
    return boardColumns.value.find((column) => column.statusIds.includes(statusId))?.id ?? null
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
      boardStore.setBoard(state.boardId)
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
    await api.moveIssue(issueId, target)
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

  async addComment(issueId, body) {
    const updated = await api.addComment(issueId, { author: session.login, body })
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
    }
  },

  async saveEntity(collection, data) {
    const exists = Boolean(data.__existing)
    delete data.__existing
    const saved = exists
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
}
