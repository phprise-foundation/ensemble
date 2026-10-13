import { isSimulated } from './simulation.js'
import { mockApi } from './mock.js'
import { realApi } from './real.js'

function pick(feature) {
  return isSimulated(feature) ? mockApi : realApi
}

export const api = {
  fetchWorkspace: () => pick('board').fetchWorkspace(),
  fetchIssues: () => pick('issues').fetchIssues(),
  fetchIssue: (id) => pick('issues').fetchIssue(id),
  createIssue: (payload) => pick('issues').createIssue(payload),
  updateIssue: (id, patch) => pick('issues').updateIssue(id, patch),
  moveIssue: (id, target) => pick('issues').moveIssue(id, target),
  addComment: (id, comment) => pick('issues').addComment(id, comment),
  deleteIssue: (id) => pick('issues').deleteIssue(id),
}

export { isSimulated, FEATURES, simulation, setFeature, setAll, simulatedFeatures, hasSimulation } from './simulation.js'
