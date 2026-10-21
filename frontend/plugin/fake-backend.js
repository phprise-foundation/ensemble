import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { createSeed } from '../src/data/seed.js'
import { avatarColorFor } from '../src/lib/avatar-color.js'
import { COMMENT_LIMIT, DESCRIPTION_LIMIT, countCharacters, limitError } from '../shared/limits.js'

const here = dirname(fileURLToPath(import.meta.url))
const dataDirectory = join(here, '..', '.data')
const dataFile = join(dataDirectory, 'db.json')

const COLLECTIONS = [
  'workspaces', 'workflows', 'statuses', 'transitions', 'issueTypes',
  'boards', 'teams', 'roles', 'profiles', 'agents', 'experiences', 'sources',
]

const DISPLAY_NAME_LIMIT = 40
const AVATAR_PATTERN = /^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+/]+={0,2})$/
const AVATAR_BASE64_LIMIT = 512 * 1024
const AVATAR_COLOR_PATTERN = /^#[0-9a-fA-F]{6}$/

function backfillUser(user) {
  user.avatar ??= null
  user.avatarColor ??= avatarColorFor(user.login)
}

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
  for (const issue of database.issues ?? []) {
    issue.history ??= []
  }
  backfillUser(database.user)
  return database
}

function userResponse(user) {
  return { login: user.login, displayName: user.displayName, avatar: user.avatar, avatarColor: user.avatarColor }
}

function displayNameProblem(value) {
  if (typeof value !== 'string') return { error: 'display name must be a string', field: 'displayName', status: 422 }
  if (value.trim().length > DISPLAY_NAME_LIMIT) {
    return { error: 'display name must be at most 40 characters', field: 'displayName', status: 422 }
  }
  return null
}

function avatarProblem(value) {
  if (value === null) return null
  const match = typeof value === 'string' ? AVATAR_PATTERN.exec(value) : null
  if (!match) return { error: 'avatar must be null or a png, jpeg or webp base64 data URL', field: 'avatar', status: 422 }
  if (match[2].length > AVATAR_BASE64_LIMIT || Buffer.from(match[2], 'base64').length > AVATAR_BASE64_LIMIT) {
    return { error: 'avatar exceeds 512 KiB', field: 'avatar', status: 422 }
  }
  return null
}

function avatarColorProblem(value) {
  if (AVATAR_COLOR_PATTERN.test(String(value))) return null
  return { error: 'avatarColor must be a hex color like #4c9aff', field: 'avatarColor', status: 422 }
}

function userChanges(body) {
  if (body.displayName !== undefined) {
    const problem = displayNameProblem(body.displayName)
    if (problem) return problem
  }
  if (body.avatar !== undefined) {
    const problem = avatarProblem(body.avatar)
    if (problem) return problem
  }
  if (body.avatarColor !== undefined) {
    const problem = avatarColorProblem(body.avatarColor)
    if (problem) return problem
  }
  return null
}

function applyUserChanges(user, body) {
  if (body.displayName !== undefined) user.displayName = body.displayName.trim()
  if (body.avatar !== undefined) user.avatar = body.avatar
  if (body.avatarColor !== undefined) user.avatarColor = body.avatarColor
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

function boardProblem(db, boardId) {
  const board = boardOf(db, boardId)
  if (!board) return { error: 'board inválido', field: 'boardId', status: 422 }
  return { board }
}

function createStatusProblem(db, board, statusId) {
  const workflowStatuses = db.statuses.filter((status) => status.workflowId === board.workflowId)
  if (statusId === undefined) {
    const initial = workflowStatuses.find((status) => status.type === 'initial')
    if (initial) return { status: initial }
    return { error: 'workflow do board não tem status inicial', field: 'statusId', status: 422 }
  }
  const status = db.statuses.find((candidate) => candidate.id === statusId)
  if (!status) return { error: 'status inexistente', field: 'statusId', status: 422 }
  if (status.workflowId !== board.workflowId) {
    return { error: 'status de outro workflow', field: 'statusId', status: 422 }
  }
  if (status.type !== 'initial') {
    return { error: 'a nova task precisa nascer em um status inicial', field: 'statusId', status: 422 }
  }
  return { status }
}

function createColumnProblem(board, status) {
  const column = board.columns.find((candidate) => candidate.statusIds.includes(status.id))
  if (!column) {
    return { error: 'status não está associado a nenhuma coluna do board', field: 'statusId', status: 422 }
  }
  return { column }
}

function limitProblem(field, value, limit) {
  const length = countCharacters(value)
  if (length <= limit) return null
  return { error: limitError(field, length, limit), field, status: 422 }
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
      const descriptionProblem = limitProblem('description', body.description ?? '', DESCRIPTION_LIMIT)
      if (descriptionProblem) return descriptionProblem
      const boardResult = boardProblem(db, body.boardId)
      if (boardResult.error) return boardResult
      const board = boardResult.board
      const statusResult = createStatusProblem(db, board, body.statusId)
      if (statusResult.error) return statusResult
      const status = statusResult.status
      const columnResult = createColumnProblem(board, status)
      if (columnResult.error) return columnResult
      const column = columnResult.column
      const issue = {
        id: nextIssueId(db, board.id),
        boardId: board.id,
        typeId: body.typeId ?? 'task',
        summary: body.summary ?? '',
        description: body.description ?? '',
        statusId: status.id,
        assigneeId: body.assigneeId ?? null,
        reporter: body.reporter ?? 'user',
        worker: '',
        order: 0,
        flags: [],
        history: [],
        comments: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      }
      db.issues.push(issue)
      issuesInColumn(db, board.id, column.id).forEach((candidate, index) => { candidate.order = index })
      return { data: issue, status: 201 }
    }
    if (method === 'PATCH' && id) {
      const issue = db.issues.find((candidate) => candidate.id === id)
      if (!issue) return { error: 'issue not found', status: 404 }
      const descriptionProblem = body.description === undefined
        ? null
        : limitProblem('description', body.description, DESCRIPTION_LIMIT)
      if (descriptionProblem) return descriptionProblem
      Object.assign(issue, body, { id, updatedAt: new Date().toISOString() })
      return { data: issue }
    }
    if (method === 'POST' && id && sub === 'move') return moveIssue(db, id, body)
    if (method === 'POST' && id && sub === 'comments') {
      const issue = db.issues.find((candidate) => candidate.id === id)
      if (!issue) return { error: 'issue not found', status: 404 }
      const commentProblem = limitProblem('comment', body.body ?? '', COMMENT_LIMIT)
      if (commentProblem) return commentProblem
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
    if (method === 'GET') return { data: userResponse(db.user) }
    if (method === 'PATCH') {
      const problem = userChanges(body)
      if (problem) {
        console.warn(`[fake-backend] PATCH /api/user recusado: ${problem.error}`)
        return problem
      }
      applyUserChanges(db.user, body)
      return { data: userResponse(db.user) }
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
    res.end(JSON.stringify(result.error ? { error: result.error, field: result.field } : result.data))
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
