import { computed, reactive } from 'vue'
import { api } from '../api/index.js'
import { isRealBackend, token, request } from '../api/client.js'
import { isSimulated } from '../api/simulation.js'
import { session } from './session.js'

const realMode = () => isRealBackend() && Boolean(token()) && Boolean(session.workspaceId)
const scoped = (path) => `/api/workspaces/${session.workspaceId}${path}`
const issueOf = (issueId) => state.issues.find((issue) => issue.id === issueId)
const presented = (payload) => payload?.issue ?? payload ?? null
const orderKeyOf = (issue) => issue.order ?? Date.parse(issue.enteredAt ?? '') ?? 0

const REAL_ROUTES = {
  workflows: { key: 'workflow', path: () => 'workflows' },
  boards: { key: 'board', path: () => 'boards' },
  teams: { key: 'team', path: () => 'teams' },
  sources: { key: 'repository', path: () => 'repositories' },
  statuses: { key: 'status', path: (data) => `workflows/${data.workflowId}/statuses` },
  transitions: { key: 'transition', path: (data) => `workflows/${data.workflowId}/transitions` },
  issueTypes: { key: 'issueType', path: (data) => `workflows/${data.workflowId}/issue-types` },
  columns: { key: 'column', path: (data) => `boards/${data.boardId}/columns` },
  roles: { key: 'role', path: (data) => `teams/${data.teamId}/roles` },
  profiles: { key: 'profile', path: (data) => `teams/${data.teamId}/profiles` },
  agents: { key: 'agent', path: (data) => `teams/${data.teamId}/agents` },
}

const LEGACY_TO_REAL_KEYS = {
  transitions: { from: 'fromStatusId', to: 'toStatusId', type: 'actor' },
}

const REAL_BODY_DROPS = {
  workflows: ['id', 'templateId', 'workspaceId'],
  boards: ['id', 'workspaceId', 'filters', 'columns'],
  teams: ['id', 'workspaceId'],
  sources: ['id', 'workspaceId', 'path'],
  statuses: ['id', 'workflowId'],
  transitions: ['id', 'workflowId'],
  issueTypes: ['id', 'workflowId'],
  columns: ['id', 'boardId'],
  roles: ['id', 'teamId'],
  profiles: ['id', 'teamId'],
  agents: ['id', 'teamId'],
}

function normalizeEntity(collection, entity) {
  const map = LEGACY_TO_REAL_KEYS[collection]
  if (!map) {
    return entity
  }
  const merged = { ...entity }
  for (const [legacy, real] of Object.entries(map)) {
    if (entity[real] !== undefined) {
      merged[legacy] = entity[real]
    }
  }
  return merged
}

function entityById(collection, id) {
  return (state[collection] ?? []).find((item) => item.id === id) ?? null
}

function realPayload(collection, data) {
  const drops = REAL_BODY_DROPS[collection] ?? ['id', 'workspaceId']
  const map = LEGACY_TO_REAL_KEYS[collection]
  const payload = {}
  for (const [key, value] of Object.entries(data)) {
    if (drops.includes(key) || key === '__existing' || value === undefined) {
      continue
    }
    payload[map?.[key] ?? key] = value
  }
  return payload
}

async function realSave(collection, data) {
  const spec = REAL_ROUTES[collection]
  if (!spec) {
    throw new Error(`${collection} is not available on the real backend yet`)
  }
  const exists = Boolean(data.__existing)
  const payload = realPayload(collection, data)
  const path = spec.path(exists ? entityById(collection, data.id) ?? data : data)
  const wrapper = exists
    ? await request('PATCH', scoped(`/${path}/${data.id}`), payload)
    : await request('POST', scoped(`/${path}`), payload)
  return normalizeEntity(collection, wrapper?.[spec.key] ?? wrapper)
}

async function realRemove(collection, id) {
  const spec = REAL_ROUTES[collection]
  if (!spec) {
    throw new Error(`${collection} is not available on the real backend yet`)
  }
  const entity = entityById(collection, id)
  if (!entity) {
    return
  }
  await request('DELETE', scoped(`/${spec.path(entity)}/${id}`))
}

async function moveReal(issueId, statusId) {
  const issue = issueOf(issueId)
  if (!issue) {
    throw new Error('Unknown issue')
  }
  return presented(await request('POST', scoped(`/issues/${issue.key}/move`), { statusId }))
}

async function commentReal(issueId, body) {
  const issue = issueOf(issueId)
  if (!issue) {
    throw new Error('Unknown issue')
  }
  return presented(await request('POST', scoped(`/issues/${issue.key}/comments`), { body }))
}

async function updateReal(issueId, patch) {
  const issue = issueOf(issueId)
  if (!issue) {
    throw new Error('Unknown issue')
  }
  return presented(await request('PATCH', scoped(`/issues/${issue.key}`), patch))
}

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
  columns: [],
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
      .filter((issue) => column.statusIds.includes(issue.statusId))
      .sort((a, b) => orderKeyOf(a) - orderKeyOf(b))
  },

  canUserMove(fromStatusId, toStatusId) {
    if (fromStatusId === toStatusId) {
      return true
    }
    return boardTransitions.value.some(
      (transition) =>
        (transition.to ?? transition.toStatusId) === toStatusId &&
        (transition.from ?? transition.fromStatusId ?? '*') === fromStatusId &&
        transition.type !== 'agent' &&
        transition.actor !== 'agent',
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
      if (realMode()) {
        const snapshot = await api.snapshot(session.workspaceId)
        for (const [key, list] of Object.entries(snapshot)) {
          if (Array.isArray(list)) {
            snapshot[key] = list.map((item) => normalizeEntity(key, item))
          }
        }
        Object.assign(state, snapshot)
      } else {
        const workspace = await api.fetchWorkspace()
        Object.assign(state, workspace)
        state.issues = await api.fetchIssues()
      }
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
    if (realMode()) {
      const moved = await moveReal(issueId, target)
      const index = state.issues.findIndex((issue) => issue.id === issueId)
      if (index >= 0) {
        state.issues.splice(index, 1, moved)
      }
      return
    }
    await api.moveIssue(issueId, target)
    state.issues = await api.fetchIssues()
  },

  async createIssue(payload) {
    if (realMode()) {
      const created = presented(await api.request('POST', scoped('/issues'), payload))
      state.issues.push(created)
      return created
    }
    const created = await api.createIssue(payload)
    state.issues = await api.fetchIssues()
    return created
  },

  async updateIssue(id, patch) {
    const updated = realMode() ? await updateReal(id, patch) : await api.updateIssue(id, patch)
    const index = state.issues.findIndex((issue) => issue.id === id)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
    }
    return updated
  },

  async addComment(issueId, body) {
    if (realMode()) {
      const updated = await commentReal(issueId, body)
      const index = state.issues.findIndex((issue) => issue.id === issueId)
      if (index >= 0) {
        state.issues.splice(index, 1, updated)
      }
      return
    }
    const updated = await api.addComment(issueId, { author: session.login, body })
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
    }
  },

  async saveEntity(collection, data) {
    if (realMode()) {
      const saved = await realSave(collection, data)
      await boardStore.load()
      return saved
    }
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
    if (realMode()) {
      await realRemove(collection, id)
      await boardStore.load()
      return
    }
    await api.deleteEntity(collection, id)
    state[collection] = state[collection].filter((item) => item.id !== id)
  },
}
