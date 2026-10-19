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
const seen = new Set()

function nextMessage(kind, actor, text) {
  seq += 1
  return { seq, at: new Date().toISOString(), kind, actor, text }
}

function push(kind, actor, text) {
  const message = nextMessage(kind, actor, text)
  state.messages.push(message)
  state.unread += 1
}

function keyOf(issueId, commentId) {
  return `${issueId}:${commentId}`
}

function commentsOf(issue) {
  return (issue.comments ?? [])
    .filter((comment) => comment.author !== 'user')
    .map((comment) => ({ key: keyOf(issue.id, comment.id), comment }))
}

function dedupe(issues) {
  const batch = new Set()
  return issues.flatMap(commentsOf).filter((entry) => {
    if (seen.has(entry.key) || batch.has(entry.key)) {
      return false
    }
    batch.add(entry.key)
    return true
  })
}

function onVisibility() {
  if (!document.hidden) {
    consoleStore.pollOnce()
  }
}

async function tick() {
  if (document.hidden) {
    return
  }
  await consoleStore.pollOnce()
}

export const consoleStore = {
  state,

  append(kind, actor, text) {
    push(kind, actor, text)
  },

  clearUnread() {
    state.unread = 0
  },

  clear() {
    state.messages = []
    state.unread = 0
  },

  async pollOnce() {
    const issues = await api.fetchIssues()
    for (const entry of dedupe(issues)) {
      seen.add(entry.key)
      push('agent', entry.comment.author, entry.comment.body)
    }
  },

  start() {
    if (state.polling || typeof window === 'undefined') {
      return
    }
    state.polling = true
    timer = window.setInterval(tick, POLL_INTERVAL_MS)
    document.addEventListener('visibilitychange', onVisibility)
  },

  stop() {
    if (!state.polling) {
      return
    }
    state.polling = false
    window.clearInterval(timer)
    timer = null
    document.removeEventListener('visibilitychange', onVisibility)
  },
}
