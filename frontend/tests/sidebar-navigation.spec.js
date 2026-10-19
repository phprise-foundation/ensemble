import { describe, expect, it } from 'vitest'
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

describe('sidebarNavigation', () => {
  it('deriva folhas de boards, workflows, teams e Experiences sem Sources', () => {
    const navigation = sidebarNavigation(store, 'ws')
    expect(navigation.boards.map((entry) => entry.leaf.title)).toEqual(['Kanban'])
    expect(navigation.workflows.map((entry) => entry.leaves.map((leaf) => leaf.label))).toEqual([
      ['Statuses', 'Transitions', 'Issue types'],
      ['Statuses', 'Transitions', 'Issue types'],
    ])
    expect(navigation.teams.map((entry) => entry.leaves.map((leaf) => leaf.label))).toEqual([
      ['Agents', 'Roles', 'Profiles'],
      ['Agents', 'Roles', 'Profiles'],
    ])
    expect(navigation.experiences.map((leaf) => leaf.to)).toEqual(['/experiences'])
    expect(JSON.stringify(navigation)).not.toContain('sources')
  })

  it('devolve modelo e rail vazios sem workspace', () => {
    expect(sidebarNavigation(store, null)).toEqual({ boards: [], workflows: [], teams: [], experiences: [] })
    expect(railSections(sidebarNavigation(store, null))).toEqual([])
  })
})

describe('railSections', () => {
  it('preserva a ordem da arvore com nomes unicos no rail', () => {
    const sections = railSections(sidebarNavigation(store, 'ws'))
    expect(sections.map((leaves) => leaves.map((leaf) => leaf.railTitle))).toEqual([
      ['Kanban'],
      ['Standard Statuses', 'Standard Transitions', 'Standard Issue types'],
      ['Flow B Statuses', 'Flow B Transitions', 'Flow B Issue types'],
      ['Default team Agents', 'Default team Roles', 'Default team Profiles'],
      ['Design Agents', 'Design Roles', 'Design Profiles'],
      ['Experiences'],
    ])
    const titles = sections.flat().map((leaf) => leaf.railTitle)
    expect(new Set(titles).size).toBe(titles.length)
  })

  it('marca cada folha com a rota do router para o destaque ativo', () => {
    const leaves = railSections(sidebarNavigation(store, 'ws')).flat()
    expect(leaves.map((leaf) => leaf.route)).toEqual([
      'board',
      'statuses',
      'transitions',
      'issue-types',
      'statuses',
      'transitions',
      'issue-types',
      'agents',
      'roles',
      'profiles',
      'agents',
      'roles',
      'profiles',
      'experiences',
    ])
    expect(leaves.every((leaf) => typeof leaf.to === 'string' && leaf.to.startsWith('/'))).toBe(true)
  })
})
