import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { createSeed } from '../src/data/seed.js'

const here = dirname(fileURLToPath(import.meta.url))
const dataDirectory = join(here, '..', '.data')
const dataFile = join(dataDirectory, 'db.json')

const COLLECTIONS = [
  'workspaces', 'workflows', 'statuses', 'transitions', 'issueTypes',
  'boards', 'teams', 'roles', 'profiles', 'agents', 'experiences', 'sources',
]

export function load(filePath = dataFile) {
  if (!existsSync(filePath)) {
    mkdirSync(dirname(filePath), { recursive: true })
    writeFileSync(filePath, JSON.stringify(createSeed(), null, 2))
  }
  const database = JSON.parse(readFileSync(filePath, 'utf-8'))
  const seed = createSeed()
  for (const key of Object.keys(seed)) {
    database[key] ??= seed[key]
  }
  return database
}

function save(db) {
  writeFileSync(dataFile, JSON.stringify(db, null, 2))
}

function slugify(text, fallback) {
  const slug = String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
  return slug || fallback || `item-${Date.now()}`
}

function sourcePathProblem(path) {
  if (!String(path ?? '').trim()) return { error: 'informe o caminho do diretório', status: 422 }
  if (!existsSync(path) || !statSync(path).isDirectory()) return { error: 'diretório não encontrado', status: 422 }
  return null
}

function sourceName(body) {
  if (String(body.name ?? '').trim()) return body.name
  return String(body.path ?? '').split('/').filter(Boolean).pop() ?? ''
}

function allowedFor(db, from, to, actor) {
  return db.transitions.some((transition) => {
    if (transition.to !== to) return false
    if (transition.from !== '*' && transition.from !== from) return false
    return actor === 'agent' ? transition.type !== 'user' : transition.type !== 'agent'
  })
}

function boardOf(db, boardId) {
  return db.boards.find((board) => board.id === boardId)
}

function issuesInColumn(db, boardId, columnId) {
  const board = boardOf(db, boardId)
  const column = board?.columns.find((candidate) => candidate.id === columnId)
  if (!column) return []
  return db.issues
    .filter((issue) => issue.boardId === boardId && column.statusIds.includes(issue.statusId))
    .sort((a, b) => a.order - b.order)
}

function nextIssueId(db, boardId) {
  const highest = db.issues.reduce((max, issue) => {
    if (issue.boardId !== boardId) return max
    const match = /-(\d+)$/.exec(issue.id)
    return match ? Math.max(max, Number(match[1])) : max
  }, 0)
  return `${boardId}-${highest + 1}`
}

function moveIssue(db, id, { columnId, statusId, order }) {
  const issue = db.issues.find((candidate) => candidate.id === id)
  const board = issue ? boardOf(db, issue.boardId) : null
  const column = board?.columns.find((candidate) => candidate.id === columnId)
  if (!issue || !board || !column) return { error: 'issue or column not found', status: 404 }
  const previousStatusId = issue.statusId
  const target = statusId ?? column.statusIds.find((candidate) => allowedFor(db, issue.statusId, candidate, 'user'))
  if (!target || !column.statusIds.includes(target)) return { error: 'no allowed transition into this column', status: 409 }
  if (target !== issue.statusId && !allowedFor(db, issue.statusId, target, 'user')) return { error: 'transition not allowed', status: 409 }
  const origin = board.columns.find((candidate) => candidate.statusIds.includes(previousStatusId))
  issue.statusId = target
  issue.updatedAt = new Date().toISOString()
  if (origin && origin.id !== columnId) {
    issuesInColumn(db, board.id, origin.id).forEach((candidate, index) => { candidate.order = index })
  }
  const list = issuesInColumn(db, board.id, columnId).filter((candidate) => candidate.id !== issue.id)
  const index = Math.max(0, Math.min(order ?? list.length, list.length))
  list.splice(index, 0, issue)
  list.forEach((candidate, position) => { candidate.order = position })
  return { issue }
}

export function handle(db, method, segments, body) {
  const [head, id, sub] = segments

  if (head === 'workspace' && method === 'GET') {
    return {
      data: {
        workspaces: db.workspaces, workflows: db.workflows, statuses: db.statuses,
        transitions: db.transitions, issueTypes: db.issueTypes, boards: db.boards,
        teams: db.teams, roles: db.roles, profiles: db.profiles, agents: db.agents,
        experiences: db.experiences, sources: db.sources,
      },
    }
  }

  if (head === 'issues') {
    if (method === 'GET' && id === undefined) return { data: db.issues.slice().sort((a, b) => a.order - b.order) }
    if (method === 'GET' && id) return { data: db.issues.find((issue) => issue.id === id) ?? null }
    if (method === 'POST' && id === undefined) {
      const board = boardOf(db, body.boardId) ?? db.boards[0]
      const workflowStatuses = db.statuses.filter((status) => status.workflowId === board.workflowId)
      const initial = workflowStatuses.find((status) => status.type === 'initial') ?? workflowStatuses[0]
      const issue = {
        id: nextIssueId(db, board.id),
        boardId: board.id,
        typeId: body.typeId ?? 'task',
        summary: body.summary ?? '',
        description: body.description ?? '',
        statusId: body.statusId ?? initial.id,
        assigneeId: body.assigneeId ?? null,
        reporter: body.reporter ?? 'user',
        worker: '',
        order: 0,
        flags: [],
        comments: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      }
      db.issues.push(issue)
      const columnId = body.columnId ?? board.columns.find((column) => column.statusIds.includes(issue.statusId))?.id
      issuesInColumn(db, board.id, columnId).forEach((candidate, index) => { candidate.order = index })
      return { data: issue, status: 201 }
    }
    if (method === 'PATCH' && id) {
      const issue = db.issues.find((candidate) => candidate.id === id)
      if (!issue) return { error: 'issue not found', status: 404 }
      Object.assign(issue, body, { id, updatedAt: new Date().toISOString() })
      return { data: issue }
    }
    if (method === 'POST' && id && sub === 'move') return moveIssue(db, id, body)
    if (method === 'POST' && id && sub === 'comments') {
      const issue = db.issues.find((candidate) => candidate.id === id)
      if (!issue) return { error: 'issue not found', status: 404 }
      issue.comments.push({ id: `c${Date.now()}`, author: body.author ?? 'user', body: body.body ?? '', at: new Date().toISOString() })
      issue.updatedAt = new Date().toISOString()
      return { data: issue }
    }
    if (method === 'DELETE' && id) {
      db.issues = db.issues.filter((issue) => issue.id !== id)
      return { data: { deleted: id } }
    }
  }

  if (head === 'sources') {
    if (method === 'GET' && id === undefined) return { data: db.sources }
    if (method === 'POST' && id === undefined) {
      const problem = sourcePathProblem(body.path)
      if (problem) return problem
      const entity = { ...body, name: sourceName(body) }
      delete entity.__existing
      if (!entity.id) entity.id = slugify(entity.name)
      if (db.sources.some((item) => item.id === entity.id)) return { error: 'source id já existe', status: 409 }
      db.sources.push(entity)
      return { data: entity, status: 201 }
    }
    if (method === 'PATCH' && id) {
      if (body.id !== undefined && body.id !== id) return { error: 'sources id divergente da URL: o id é imutável', status: 409 }
      const entity = db.sources.find((item) => item.id === id)
      if (!entity) return { error: 'source não encontrada', status: 404 }
      if (body.path !== undefined) {
        const problem = sourcePathProblem(body.path)
        if (problem) return problem
      }
      Object.assign(entity, body, { id })
      return { data: entity }
    }
    if (method === 'DELETE' && id) {
      db.sources = db.sources.filter((item) => item.id !== id)
      return { data: { deleted: id } }
    }
  }

  if (head === 'user') {
    if (method === 'GET') return { data: { login: db.user.login, displayName: db.user.displayName } }
    if (method === 'PATCH') {
      const displayName = String(body.displayName ?? '').trim()
      if (displayName.length > 40) return { error: 'display name must be at most 40 characters', status: 422 }
      db.user.displayName = displayName
      return { data: { login: db.user.login, displayName: db.user.displayName } }
    }
  }

  if (COLLECTIONS.includes(head)) {
    const list = db[head]
    if (method === 'GET' && id === undefined) return { data: list }
    if (method === 'POST' && id === undefined) {
      const entity = { ...body }
      delete entity.__existing
      if (!entity.id) {
        entity.id = entity.name ? slugify(entity.name) : `item-${Date.now()}`
      }
      if (list.some((item) => item.id === entity.id)) return { error: `${head} id already exists`, status: 409 }
      list.push(entity)
      return { data: entity, status: 201 }
    }
    if (method === 'PATCH' && id) {
      if (body.id !== undefined && body.id !== id) return { error: `${head} id divergente da URL: o id é imutável`, status: 409 }
      const entity = list.find((item) => item.id === id)
      if (!entity) return { error: `${head} not found`, status: 404 }
      Object.assign(entity, body, { id })
      return { data: entity }
    }
    if (method === 'DELETE' && id) {
      db[head] = list.filter((item) => item.id !== id)
      return { data: { deleted: id } }
    }
  }

  return { error: 'not found', status: 404 }
}

function middleware(data, req, res, next) {
  if (!req.url || !req.url.startsWith('/api/')) {
    next()
    return
  }
  const url = new URL(req.url, 'http://localhost')
  const segments = url.pathname.replace(/^\/api\/?/, '').split('/').filter(Boolean)
  const chunks = []
  req.on('data', (chunk) => chunks.push(chunk))
  req.on('end', () => {
    let body = {}
    if (chunks.length) {
      try {
        body = JSON.parse(Buffer.concat(chunks).toString())
      } catch {
        body = {}
      }
    }
    let result
    try {
      result = handle(data.db, req.method, segments, body)
    } catch (error) {
      result = { error: error.message, status: 500 }
    }
    if (!result.error) {
      save(data.db)
    }
    res.statusCode = result.status ?? 200
    res.setHeader('Content-Type', 'application/json')
    res.end(JSON.stringify(result.error ? { error: result.error } : result.data))
  })
}

export function fakeBackend() {
  return {
    name: 'fake-backend',
    configureServer(server) {
      const data = { db: load() }
      server.middlewares.use((req, res, next) => middleware(data, req, res, next))
    },
    configurePreviewServer(server) {
      const data = { db: load() }
      server.middlewares.use((req, res, next) => middleware(data, req, res, next))
    },
  }
}
