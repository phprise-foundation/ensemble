import assert from 'node:assert/strict'
import { test } from 'node:test'
import { railSections, sidebarNavigation } from '../src/navigation.js'

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
  assert.deepEqual(sidebarNavigation(store, null), { boards: [], workflows: [], teams: [], experiences: [] })
  assert.deepEqual(railSections(sidebarNavigation(store, null)), [])
})

test('rail expoe as folhas de boards, workflows, teams e Experiences', () => {
  const sections = railSections(sidebarNavigation(store, 'ws'))
  assert.deepEqual(sections.map((leaves) => leaves.length), [1, 3, 3, 3, 3, 1])
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
