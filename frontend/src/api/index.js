import { request } from './client.js'

export { FEATURES, simulation, isSimulated, setFeature, setAll, simulatedFeatures, hasSimulation } from './simulation.js'

export { request, token, setToken, isRealBackend } from './client.js'

export const api = {
  login: (credentials) => request('POST', '/api/auth/login', credentials),
  logout: () => request('POST', '/api/auth/logout'),
  me: () => request('GET', '/api/me'),
  workspaces: () => request('GET', '/api/workspaces'),
  snapshot: (workspaceId) => request('GET', `/api/workspaces/${workspaceId}/snapshot`),

  fetchUser: () => request('GET', '/api/user'),
  updateUser: (data) => request('PATCH', '/api/user', data),

  fetchWorkspace: () => request('GET', '/api/workspace'),

  fetchIssues: () => request('GET', '/api/issues'),
  fetchIssue: (id) => request('GET', `/api/issues/${id}`),
  createIssue: (payload) => request('POST', '/api/issues', payload),
  updateIssue: (id, patch) => request('PATCH', `/api/issues/${id}`, patch),
  moveIssue: (id, target) => request('POST', `/api/issues/${id}/move`, target),
  addComment: (id, comment) => request('POST', `/api/issues/${id}/comments`, comment),
  deleteIssue: (id) => request('DELETE', `/api/issues/${id}`),

  createEntity: (collection, data) => request('POST', `/api/${collection}`, data),
  updateEntity: (collection, id, data) => request('PATCH', `/api/${collection}/${id}`, data),
  deleteEntity: (collection, id) => request('DELETE', `/api/${collection}/${id}`),
}
