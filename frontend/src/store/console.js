import { reactive } from 'vue'
import { api } from '../api/index.js'

const POLL_INTERVAL_MS = 5000
const SYSTEM_ACTOR = 'system'

const state = reactive({
  messages: [],
  busy: false,
  unread: 0,
  polling: false,
})

let seq = 0
let timer = null
let inFlight = null
let failureReported = false
const seen = new Set()

function stampOf(comment) {
  return comment.created_at ?? comment.at ?? ''
}

function textOf(comment) {
  return String(comment.body ?? '')
}

function fingerprint(text) {
  let hash = 5381
  for (let index = 0; index < text.length; index += 1) {
    hash = ((hash << 5) + hash + text.charCodeAt(index)) >>> 0
  }
  return hash.toString(36)
}

function keyOf(issueId, comment) {
  if (comment.id !== undefined && comment.id !== null) {
    return `${issueId}:${comment.id}`
  }
  return `${issueId}:${comment.author}@${stampOf(comment)}#${fingerprint(textOf(comment))}`
}

function nextMessage({ actor, kind, text, at }) {
  seq += 1
  return { seq, at: at ?? new Date().toISOString(), actor, kind, text }
}

function appendEntry(comment) {
  consoleStore.append({
    actor: comment.author,
    kind: 'agent',
    text: textOf(comment),
    at: stampOf(comment),
  })
}

function visible(comment) {
  return comment.author !== 'user'
}

function entriesOf(issue) {
  const comments = (issue.comments ?? []).filter(visible)
  return comments.map((comment) => ({ key: keyOf(issue.id, comment), comment }))
}

function isNew(entry, batch) {
  return !seen.has(entry.key) && !batch.has(entry.key)
}

function absorb(entries) {
  const batch = new Set()
  for (const entry of entries) {
    if (isNew(entry, batch)) {
      batch.add(entry.key)
      seen.add(entry.key)
      appendEntry(entry.comment)
    }
  }
}

async function collect() {
  const issues = await api.fetchIssues()
  const entries = (issues ?? []).flatMap(entriesOf)
  absorb(entries)
}

function reportFailure(error) {
  if (failureReported) {
    return
  }
  failureReported = true
  consoleStore.append({
    actor: SYSTEM_ACTOR,
    kind: 'error',
    text: `Falha ao consultar o board: ${error?.message ?? error}`,
    at: new Date().toISOString(),
  })
}

function run() {
  inFlight = collect()
    .then(() => {
      failureReported = false
    })
    .catch(reportFailure)
    .finally(() => {
      inFlight = null
    })
  return inFlight
}

export async function pollTick() {
  if (document.hidden) {
    return
  }
  try {
    await consoleStore.pollOnce()
  } catch (error) {
    reportFailure(error)
  }
}

export const consoleStore = {
  state,

  append(draft) {
    state.messages.push(nextMessage(draft))
    state.unread += 1
  },

  clearUnread() {
    state.unread = 0
  },

  resetDedupe() {
    seen.clear()
    failureReported = false
  },

  clear() {
    state.messages = []
    state.unread = 0
  },

  pollOnce() {
    return inFlight ?? run()
  },

  start() {
    if (state.polling || typeof window === 'undefined') {
      return
    }
    state.polling = true
    timer = window.setInterval(pollTick, POLL_INTERVAL_MS)
    document.addEventListener('visibilitychange', pollTick)
  },

  stop() {
    if (!state.polling) {
      return
    }
    state.polling = false
    window.clearInterval(timer)
    timer = null
    document.removeEventListener('visibilitychange', pollTick)
  },
}
