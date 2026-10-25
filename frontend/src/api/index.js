import { request, token, setToken } from './client.js'

export { FEATURES, simulation, isSimulated, setFeature, setAll, simulatedFeatures, hasSimulation } from './simulation.js'
export { request, token, setToken } from './client.js'

export const api = {
  login: (credentials) => request('POST', '/api/auth/login', credentials),
  logout: () => request('POST', '/api/auth/logout'),
  me: () => request('GET', '/api/me'),
  workspaces: () => request('GET', '/api/workspaces'),
  snapshot: (workspaceId) => request('GET', `/api/workspaces/${workspaceId}/snapshot`),
}
