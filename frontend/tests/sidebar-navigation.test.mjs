import assert from 'node:assert/strict'
import { test } from 'node:test'
import { isActiveLeaf, railSections, sidebarNavigation } from '../src/navigation.js'

function storeWith({ boards = [], workflows = [], teams = [] }) {
  return {
    boardsOf: (workspaceId) => boards.filter((board) => board.workspaceId === workspaceId),
    workflowsOf: (workspaceId) => workflows.filter((workflow) => workflow.workspaceId === workspaceId),
    teamsOf: (workspaceId) => teams.filter((team) => team.workspaceId === workspaceId),
  }
}

const store = storeWith({
  boards: [{ id: 'KANB', name: 'Kanban', workspaceId: 'ws' }],
  workflows: [
    { id: 'standard', name: 'Standard', workspaceId: 'ws' },
    { id: 'flow-b', name: 'Flow B', workspaceId: 'ws' },
  ],
  teams: [
    { id: 'default', name: 'Default team', workspaceId: 'ws' },
    { id: 'design', name: 'Design', workspaceId: 'ws' },
  ],
})

test('workspace nulo deixa modelo e rail vazios', () => {
  assert.deepEqual(sidebarNavigation(store, null), { boards: [], workflows: [], teams: [] })
  assert.deepEqual(railSections(sidebarNavigation(store, null)), [])
})

test('rail expoe as folhas de boards, workflows e teams', () => {
  const sections = railSections(sidebarNavigation(store, 'ws'))
  assert.deepEqual(sections.map((leaves) => leaves.length), [2, 3, 3, 3, 3])
})

test('cada workflow e team expoe suas tres paginas', () => {
  const navigation = sidebarNavigation(store, 'ws')
  assert.deepEqual(navigation.workflows[0].leaves.map((leaf) => leaf.label), ['Statuses', 'Transitions', 'Issue types'])
  assert.deepEqual(navigation.teams[0].leaves.map((leaf) => leaf.label), ['Agents', 'Roles', 'Profiles'])
})

test('Sources nao entra no rail', () => {
  const labels = railSections(sidebarNavigation(store, 'ws')).flat().map((leaf) => leaf.label)
  assert.equal(labels.includes('Sources'), false)
})

test('railTitle do workflow e unico e prefixado pelo nome', () => {
  const leaf = sidebarNavigation(store, 'ws').workflows[1].leaves[0]
  assert.equal(leaf.railTitle, 'Flow B · Statuses')
})

test('link ativo considera o id do pai e destaca uma folha por vez', () => {
  const leaves = railSections(sidebarNavigation(store, 'ws')).flat()
  const boardCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'board', params: { boardId: 'KANB' } }))
  const workflowCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'statuses', params: { workflowId: 'flow-b' } }))
  const teamCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'profiles', params: { teamId: 'design' } }))
  const boardChosen = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'board', params: {} }, { id: 'KANB', name: 'Kanban' }))
  assert.deepEqual(boardCurrent.map((leaf) => leaf.railTitle), ['Kanban'])
  assert.deepEqual(workflowCurrent.map((leaf) => leaf.railTitle), ['Flow B · Statuses'])
  assert.deepEqual(teamCurrent.map((leaf) => leaf.railTitle), ['Design · Profiles'])
  assert.deepEqual(boardChosen.map((leaf) => leaf.railTitle), ['Kanban'])
})

test('isActiveLeaf em rota sem id e sem board escolhido nao destaca nada', () => {
  const leaves = railSections(sidebarNavigation(store, 'ws')).flat()
  assert.deepEqual(leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'board', params: {} }, null)), [])
})
