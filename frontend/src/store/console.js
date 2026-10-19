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

function nextMessage({ actor, kind, text, at }) {
  seq += 1
  return { seq, at: at ?? new Date().toISOString(), actor, kind, text }
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

  clear() {
    state.messages = []
    state.unread = 0
  },

  async pollOnce() {
    const issues = await api.fetchIssues()
    for (const issue of issues) {
      for (const comment of issue.comments ?? []) {
        absorb(issue.id, comment)
      }
    }
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

function absorb(issueId, comment) {
  const key = `${issueId}:${comment.id}`
  if (comment.author === 'user' || seen.has(key)) {
    return
  }
  seen.add(key)
  consoleStore.append({ actor: comment.author, kind: 'agent', text: comment.body, at: comment.at })
}

async function pollTick() {
  if (document.hidden) {
    return
  }
  await consoleStore.pollOnce()
}
