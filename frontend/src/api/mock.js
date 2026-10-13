import { createSeed } from '../data/seed.js'

const db = createSeed()
let issueCounter = db.issues.length

const delay = (ms = 120) => new Promise((resolve) => setTimeout(resolve, ms))
const clone = (value) => structuredClone(value)

function allowedFor(from, to, actor) {
  return db.transitions.some((transition) => {
    if (transition.to !== to) {
      return false
    }
    if (transition.from !== '*' && transition.from !== from) {
      return false
    }
    return actor === 'agent' ? transition.type !== 'user' : transition.type !== 'agent'
  })
}

function issuesInColumn(columnId) {
  const column = db.columns.find((candidate) => candidate.id === columnId)
  return db.issues
    .filter((issue) => column.statusIds.includes(issue.statusId))
    .sort((a, b) => a.order - b.order)
}

function reindex(columnId) {
  issuesInColumn(columnId).forEach((issue, index) => {
    issue.order = index
  })
}

function reindexAll() {
  db.columns.forEach((column) => reindex(column.id))
}

function touch(issue) {
  issue.updatedAt = new Date().toISOString()
}

export const mockApi = {
  async fetchWorkspace() {
    await delay()
    return clone({
      boards: db.boards,
      columns: db.columns,
      statuses: db.statuses,
      transitions: db.transitions,
      issueTypes: db.issueTypes,
      roles: db.roles,
      profiles: db.profiles,
      agents: db.agents,
      experiences: db.experiences,
    })
  },

  async fetchIssues() {
    await delay(80)
    return clone(db.issues.slice().sort((a, b) => a.order - b.order))
  },

  async fetchIssue(id) {
    await delay(60)
    return clone(db.issues.find((issue) => issue.id === id) ?? null)
  },

  async createIssue(payload) {
    await delay()
    issueCounter += 1
    const issue = {
      id: `KAN-${issueCounter}`,
      boardId: payload.boardId ?? 'kanban',
      typeId: payload.typeId ?? 'task',
      summary: payload.summary ?? '',
      description: payload.description ?? '',
      statusId: payload.statusId ?? db.statuses.find((status) => status.type === 'initial').id,
      assigneeId: payload.assigneeId ?? null,
      reporter: payload.reporter ?? 'user',
      worker: '',
      order: issuesInColumn(payload.columnId ?? db.columns[0].id).length,
      flags: [],
      comments: [],
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    }
    db.issues.push(issue)
    reindexAll()
    return clone(issue)
  },

  async updateIssue(id, patch) {
    await delay(80)
    const issue = db.issues.find((candidate) => candidate.id === id)
    if (!issue) {
      throw new Error('issue not found')
    }
    Object.assign(issue, patch)
    touch(issue)
    reindexAll()
    return clone(issue)
  },

  async moveIssue(id, { columnId, statusId, order }) {
    await delay(60)
    const issue = db.issues.find((candidate) => candidate.id === id)
    const column = db.columns.find((candidate) => candidate.id === columnId)
    if (!issue || !column) {
      throw new Error('issue or column not found')
    }
    const previousStatusId = issue.statusId
    const target = statusId ?? column.statusIds.find((candidate) => allowedFor(issue.statusId, candidate, 'user'))
    if (!target || !column.statusIds.includes(target)) {
      throw new Error('no allowed transition into this column')
    }
    if (target !== issue.statusId && !allowedFor(issue.statusId, target, 'user')) {
      throw new Error('transition not allowed')
    }
    const origin = db.columns.find((candidate) => candidate.statusIds.includes(previousStatusId))
    issue.statusId = target
    touch(issue)
    if (origin && origin.id !== columnId) {
      reindex(origin.id)
    }
    const list = issuesInColumn(columnId).filter((candidate) => candidate.id !== issue.id)
    const index = Math.max(0, Math.min(order ?? list.length, list.length))
    list.splice(index, 0, issue)
    list.forEach((candidate, position) => {
      candidate.order = position
    })
    return clone(issue)
  },

  async addComment(id, { author = 'user', body }) {
    await delay(80)
    const issue = db.issues.find((candidate) => candidate.id === id)
    if (!issue) {
      throw new Error('issue not found')
    }
    issue.comments.push({ id: `c${Date.now()}`, author, body, at: new Date().toISOString() })
    touch(issue)
    return clone(issue)
  },

  async deleteIssue(id) {
    await delay(60)
    db.issues = db.issues.filter((issue) => issue.id !== id)
    reindexAll()
    return true
  },
}
