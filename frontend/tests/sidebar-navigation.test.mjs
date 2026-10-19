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

const navigation = sidebarNavigation(store, 'ws')
const sections = railSections(navigation)

test('modelo traz um board, dois workflows, dois teams e Experiences', () => {
  assert.equal(navigation.boards.length, 1)
  assert.equal(navigation.workflows.length, 2)
  assert.equal(navigation.teams.length, 2)
  assert.deepEqual(navigation.experiences.flatMap((entry) => entry.leaves.map((leaf) => leaf.to)), ['/experiences'])
})

test('ordem do rail: boards, workflows, teams e Experiences', () => {
  assert.deepEqual(
    sections.map((leaves) => leaves.map((leaf) => leaf.railTitle)),
    [
      ['Kanban'],
      ['Standard Statuses', 'Standard Transitions', 'Standard Issue types'],
      ['Flow B Statuses', 'Flow B Transitions', 'Flow B Issue types'],
      ['Default team Agents', 'Default team Roles', 'Default team Profiles'],
      ['Design Agents', 'Design Roles', 'Design Profiles'],
      ['Experiences'],
    ],
  )
})

test('Sources ficam fora do modelo e do rail', () => {
  assert.doesNotMatch(JSON.stringify(navigation), /sources/i)
  assert.ok(sections.flat().every((leaf) => leaf.feature !== 'sources'))
})

test('workspace nulo devolve modelo e rail vazios', () => {
  assert.deepEqual(sidebarNavigation(store, null), { boards: [], workflows: [], teams: [], experiences: [] })
  assert.deepEqual(railSections(sidebarNavigation(store, null)), [])
})

test('cada folha do rail tem titulo unico', () => {
  const titles = sections.flat().map((leaf) => leaf.railTitle)
  assert.equal(new Set(titles).size, titles.length)
})

test('link ativo considera o id do pai e destaca uma folha por vez', () => {
  const leaves = sections.flat()
  const boardCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'board', params: { boardId: 'KANB' } }))
  const workflowCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'statuses', params: { workflowId: 'flow-b' } }))
  const teamCurrent = leaves.filter((leaf) => isActiveLeaf(leaf, { name: 'profiles', params: { teamId: 'design' } }))
  assert.deepEqual(boardCurrent.map((leaf) => leaf.railTitle), ['Kanban'])
  assert.deepEqual(workflowCurrent.map((leaf) => leaf.railTitle), ['Flow B Statuses'])
  assert.deepEqual(teamCurrent.map((leaf) => leaf.railTitle), ['Design Profiles'])
})
