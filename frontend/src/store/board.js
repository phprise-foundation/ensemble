import { computed, reactive } from 'vue'
import { token, request } from '../api/client.js'
import { session } from './session.js'

const scoped = (path) => `/api/workspaces/${session.workspaceId}${path}`
const issueOf = (issueId) => state.issues.find((issue) => issue.id === issueId)
const presented = (payload) => normalizeEntity('issues', payload?.issue ?? payload ?? null)
const orderKeyOf = (issue) => issue.position ?? Date.parse(issue.enteredAt ?? '') ?? 0

const REAL_ROUTES = {
  workspaces: { key: 'workspace', path: () => '/api/workspaces', editPath: (data) => `/api/workspaces/${data.id}` },
  workflows: { key: 'workflow', path: () => 'workflows' },
  boards: { key: 'board', path: () => 'boards' },
  teams: { key: 'team', path: () => 'teams' },
  sources: { key: 'repository', path: () => 'repositories' },
  statuses: { key: 'status', path: (data) => `workflows/${data.workflowId}/statuses`, editPath: (data) => `statuses/${data.id}` },
  transitions: { key: 'transition', path: (data) => `workflows/${data.workflowId}/transitions`, editPath: (data) => `transitions/${data.id}` },
  issueTypes: { key: 'issueType', path: (data) => `workflows/${data.workflowId}/issue-types`, editPath: (data) => `issue-types/${data.id}` },
  fields: { key: 'field', path: (data) => `issue-types/${data.issueTypeId}/fields`, editPath: (data) => `fields/${data.id}` },
  columns: { key: 'column', path: (data) => `boards/${data.boardId}/columns`, editPath: (data) => `columns/${data.id}` },
  roles: { key: 'role', path: (data) => `teams/${data.teamId}/roles`, editPath: (data) => `roles/${data.id}` },
  profiles: { key: 'profile', path: (data) => `teams/${data.teamId}/profiles`, editPath: (data) => `profiles/${data.id}` },
  agents: { key: 'agent', path: (data) => `teams/${data.teamId}/agents`, editPath: (data) => `agents/${data.id}` },
}

const LEGACY_TO_REAL_KEYS = {
  transitions: { from: 'fromStatusId', to: 'toStatusId', type: 'actor' },
}

const REAL_BODY_DROPS = {
  workflows: ['id', 'templateId', 'workspaceId'],
  boards: ['id', 'workspaceId', 'filters', 'columns'],
  teams: ['id', 'workspaceId'],
  sources: ['id', 'workspaceId'],
  workspaces: ['id', 'prefix', 'role', 'createdAt', 'updatedAt'],
  statuses: ['id', 'workflowId'],
  transitions: ['id', 'workflowId'],
  issueTypes: ['id', 'workflowId', 'fields'],
  fields: ['id', 'issueTypeId'],
  columns: ['id', 'boardId'],
  roles: ['id', 'teamId'],
  profiles: ['id', 'teamId'],
  agents: ['id'],
}

const backendPath = (path) => (path.startsWith('/api/') ? path : scoped(`/${path}`))

function normalizeEntity(collection, entity) {
  if (collection === 'issues' && entity && typeof entity === 'object') {
    return {
      ...entity,
      id: entity.key ?? entity.id,
      comments: entity.comments ?? [],
      history: entity.history ?? [],
      runs: entity.runs ?? [],
      repositoryId: entity.repositoryId ?? null,
      workdirState: entity.workdirState ?? null,
      readyToMerge: entity.readyToMerge ?? false,
    }
  }
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
  const entity = exists ? entityById(collection, data.id) ?? data : data
  const path = exists
    ? spec.editPath ? spec.editPath(entity) : `${spec.path(entity)}/${data.id}`
    : spec.path(entity)
  const wrapper = exists
    ? await request('PATCH', backendPath(path), payload)
    : await request('POST', backendPath(path), payload)
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
  const path = spec.editPath ? spec.editPath(entity) : `${spec.path(entity)}/${id}`
  await request('DELETE', backendPath(path))
}

async function moveReal(issueId, target) {
  const issue = issueOf(issueId)
  if (!issue) {
    throw new Error('Unknown issue')
  }
  const statusId = typeof target === 'string' ? target : target?.statusId
  const position = typeof target === 'object' ? target?.position : undefined
  await request('POST', scoped(`/issues/${issue.key}/move`), {
    statusId,
    ...(position === undefined ? {} : { position }),
  })
  return presented(await request('GET', scoped(`/issues/${issue.key}`)))
}

async function commentReal(issueId, body) {
  const issue = issueOf(issueId)
  if (!issue) {
    throw new Error('Unknown issue')
  }
  await request('POST', scoped(`/issues/${issue.key}/comments`), { body })
  return presented(await request('GET', scoped(`/issues/${issue.key}`)))
}

async function hydrateIssue(issue) {
  if (!issue?.key) {
    return
  }
  const [comments, events, runs] = await Promise.all([
    request('GET', scoped(`/issues/${issue.key}/comments`)).catch(() => ({ comments: [] })),
    request('GET', scoped(`/issues/${issue.key}/events`)).catch(() => ({ events: [] })),
    request('GET', scoped(`/issues/${issue.key}/runs`)).catch(() => ({ runs: [] })),
  ])
  const target = issueOf(issue.id)
  if (!target) {
    return
  }
  target.comments = (comments?.comments ?? []).map((entry) => ({ ...entry, at: entry.createdAt ?? entry.at }))
  target.history = (events?.events ?? []).map((event) => ({
    type: event.type ?? (event.fromStatusId ? 'status' : 'created'),
    actor: event.actor,
    at: event.createdAt,
    from: event.fromStatusId,
    to: event.toStatusId,
    summary: event.summary ?? null,
  }))
  target.runs = runs?.runs ?? []
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
  fields: [],
  boards: [],
  teams: [],
  roles: [],
  profiles: [],
  agents: [],
  experiences: [],
  sources: [],
  secrets: [],
  issues: [],
  columns: [],
  boardId: null,
  selectedIssueId: null,
})

const board = computed(() => find(state.boards, state.boardId))
const boardColumns = computed(() =>
  state.columns
    .filter((column) => column.boardId === board.value?.id)
    .slice()
    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0)),
)
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


  async load() {
    state.loading = true
    state.error = ''
    try {
      const snapshot = await request('GET', scoped('/snapshot'))
      for (const [key, list] of Object.entries(snapshot)) {
        if (Array.isArray(list)) {
          snapshot[key] = list.map((item) => normalizeEntity(key, item))
        }
      }
      snapshot.fields = (snapshot.issueTypes ?? []).flatMap((type) =>
        (type.fields ?? []).map((field) => ({ ...field, issueTypeId: type.id })),
      )
      Object.assign(state, snapshot)
      boardStore.setBoard(state.boardId)
    } catch (error) {
      state.error = error.message
    } finally {
      state.loading = false
    }
  },

  select(id) {
    state.selectedIssueId = id
    void hydrateIssue(issueOf(id))
  },

  clearSelection() {
    state.selectedIssueId = null
  },

  async moveIssue(issueId, target) {
    await moveReal(issueId, target)
    await boardStore.load()
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    if (index >= 0) {
      void hydrateIssue(state.issues[index])
    }
  },

  async createIssue(payload) {
    const created = presented(await request('POST', scoped('/issues'), payload))
    state.issues.push(created)
    return created
  },

  async updateIssue(id, patch) {
    const updated = await updateReal(id, patch)
    const index = state.issues.findIndex((issue) => issue.id === id)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
      void hydrateIssue(state.issues[index])
    }
    return updated
  },

  async addComment(issueId, body) {
    const updated = await commentReal(issueId, body)
    const index = state.issues.findIndex((issue) => issue.id === issueId)
    if (index >= 0) {
      state.issues.splice(index, 1, updated)
      void hydrateIssue(state.issues[index])
    }
  },

  async loadSecrets() {
    const payload = await request('GET', scoped('/secrets'))
    state.secrets = payload.secrets ?? []
  },

  async createSecret(name, value) {
    const payload = await request('POST', scoped('/secrets'), { name, value })
    state.secrets.push(payload.secret)
    return payload.secret
  },

  async rotateSecret(id, value) {
    const payload = await request('PATCH', scoped(`/secrets/${id}`), { value })
    const index = state.secrets.findIndex((secret) => secret.id === id)
    if (index >= 0) {
      state.secrets.splice(index, 1, payload.secret)
    }
    return payload.secret
  },

  async deleteSecret(id) {
    await request('DELETE', scoped(`/secrets/${id}`))
    state.secrets = state.secrets.filter((secret) => secret.id !== id)
  },

  async probeRepository({ provider, url, secretId }) {
    return request('POST', scoped('/repositories/test'), { provider, url, secretId })
  },

  async refreshIssue(issueId) {
    const issue = issueOf(issueId)
    if (issue) {
      await hydrateIssue(issue)
    }
  },

  async requestRun(issueId) {
    const issue = issueOf(issueId)
    if (!issue) {
      throw new Error('Unknown issue')
    }
    const payload = await request('POST', scoped(`/issues/${issue.key}/runs`))
    issue.runs = [...(issue.runs ?? []), payload.run]
    await hydrateIssue(issue)
    return payload.run
  },

  async attachRepository(issueId, repositoryId) {
    const issue = issueOf(issueId)
    if (!issue) {
      throw new Error('Unknown issue')
    }
    const payload = await request('POST', scoped(`/issues/${issue.key}/repository`), { repositoryId })
    issue.repositoryId = payload?.binding?.repositoryId ?? repositoryId
    issue.workdirState = payload?.binding?.state ?? issue.workdirState
    await hydrateIssue(issue)
    return payload
  },

  async resolveWorkdir(issueId, strategy) {
    const issue = issueOf(issueId)
    if (!issue) {
      throw new Error('Unknown issue')
    }
    const payload = await request('POST', scoped(`/issues/${issue.key}/workdir/resolve`), { strategy })
    issue.repositoryId = payload?.binding?.repositoryId ?? issue.repositoryId
    issue.workdirState = payload?.binding?.state ?? issue.workdirState
    await hydrateIssue(issue)
    return payload
  },

  async reorderColumns(boardId, columnIds) {
    await request('POST', scoped(`/boards/${boardId}/columns/reorder`), { columnIds })
    await boardStore.load()
  },

  async saveEntity(collection, data) {
    const saved = await realSave(collection, data)
    await boardStore.load()
    return saved
  },

  async removeEntity(collection, id) {
    await realRemove(collection, id)
    await boardStore.load()
  },
}
