const base = import.meta.env.VITE_API_URL || '/api'

function notImplemented() {
  return Promise.reject(new Error('Backend endpoint not implemented yet'))
}

async function request(method, path, body) {
  const headers = { 'Content-Type': 'application/json' }
  const token = localStorage.getItem('board_token')
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }
  const response = await fetch(base + path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}`)
  }
  return response.json()
}

export const realApi = {
  request,
  fetchWorkspace: notImplemented,
  fetchIssues: notImplemented,
  fetchIssue: notImplemented,
  createIssue: notImplemented,
  updateIssue: notImplemented,
  moveIssue: notImplemented,
  addComment: notImplemented,
  deleteIssue: notImplemented,
}
