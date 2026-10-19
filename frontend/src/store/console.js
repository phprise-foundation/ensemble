import { reactive } from 'vue'
import { api } from '../api/index.js'

const POLL_INTERVAL_MS = 5000

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
const absorbed = new Map()

function nextMessage({ actor, kind, text, at }) {
  seq += 1
  return { seq, at: at ?? new Date().toISOString(), kind, actor, text }
}

function absorb(issue) {
  const comments = issue.comments ?? []
  const cursor = absorbed.get(issue.id) ?? 0
  for (const comment of comments.slice(cursor)) {
    if (comment.author !== 'user') {
      consoleStore.append({
        actor: comment.author,
        kind: 'agent',
        text: comment.body,
        at: comment.created_at,
      })
    }
  }
  absorbed.set(issue.id, comments.length)
}

async function collect() {
  const issues = await api.fetchIssues()
  for (const issue of issues ?? []) {
    absorb(issue)
  }
}

function reportFailure(error) {
  if (failureReported) {
    return
  }
  failureReported = true
  consoleStore.append({
    actor: 'system',
    kind: 'error',
    text: `Falha ao consultar o board: ${error?.message ?? error}`,
  })
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
    absorbed.clear()
    failureReported = false
  },

  clear() {
    state.messages = []
    state.unread = 0
  },

  pollOnce() {
    if (inFlight) {
      return inFlight
    }
    inFlight = collect()
      .then(() => {
        failureReported = false
      })
      .catch(reportFailure)
      .finally(() => {
        inFlight = null
      })
    return inFlight
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
