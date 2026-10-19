import { describe, expect, it } from 'vitest'
import { isActiveLeaf, railSections, sidebarNavigation } from '../src/navigation.js'

function storeWith({ boards = [], workflows = [], teams = [] }) {
  return {
    boardsOf: (workspaceId) => boards.filter((board) => board.workspaceId === workspaceId),
    workflowsOf: (workspaceId) => workflows.filter((workflow) => workflow.workspaceId === workspaceId),
    teamsOf: (workspaceId) => teams.filter((team) => team.workspaceId === workspaceId),
  }
}

const store = storeWith({
  boards: [
    { id: 'KANB', name: 'Kanban', workspaceId: 'ws' },
    { id: 'B2', name: 'Board 2', workspaceId: 'ws' },
  ],
  workflows: [
    { id: 'standard', name: 'Standard', workspaceId: 'ws' },
    { id: 'flow-b', name: 'Flow B', workspaceId: 'ws' },
  ],
  teams: [
    { id: 'default', name: 'Default team', workspaceId: 'ws' },
    { id: 'design', name: 'Design', workspaceId: 'ws' },
  ],
})

function routeOf(name, params) {
  return { name, params }
}

const noChosenBoard = null

describe('sidebarNavigation', () => {
  it('deriva folhas de boards, workflows, teams e Experiences sem Sources', () => {
    const navigation = sidebarNavigation(store, 'ws')
    expect(navigation.boards.map((entry) => entry.leaves.map((leaf) => leaf.title))).toEqual([['Kanban'], ['Board 2']])
    expect(navigation.workflows.map((entry) => entry.leaves.map((leaf) => leaf.label))).toEqual([
      ['Statuses', 'Transitions', 'Issue types'],
      ['Statuses', 'Transitions', 'Issue types'],
    ])
    expect(navigation.teams.map((entry) => entry.leaves.map((leaf) => leaf.label))).toEqual([
      ['Agents', 'Roles', 'Profiles'],
      ['Agents', 'Roles', 'Profiles'],
    ])
    expect(navigation.experiences.flatMap((entry) => entry.leaves.map((leaf) => leaf.to))).toEqual(['/experiences'])
    expect(JSON.stringify(navigation)).not.toContain('sources')
  })

  it('usa o mesmo formato de entrada em todas as seções', () => {
    const navigation = sidebarNavigation(store, 'ws')
    const entries = [...navigation.boards, ...navigation.workflows, ...navigation.teams, ...navigation.experiences]
    expect(entries.every((entry) => entry.entity && Array.isArray(entry.leaves) && entry.leaves.length > 0)).toBe(true)
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
      ['Board 2'],
      ['Standard Statuses', 'Standard Transitions', 'Standard Issue types'],
      ['Flow B Statuses', 'Flow B Transitions', 'Flow B Issue types'],
      ['Default team Agents', 'Default team Roles', 'Default team Profiles'],
      ['Design Agents', 'Design Roles', 'Design Profiles'],
      ['Experiences'],
    ])
    const titles = sections.flat().map((leaf) => leaf.railTitle)
    expect(new Set(titles).size).toBe(titles.length)
  })

  it('nomeia a rota de cada folha com os parametros do pai', () => {
    const leaves = railSections(sidebarNavigation(store, 'ws')).flat()
    expect(leaves.map((leaf) => leaf.route)).toContainEqual({ name: 'board', params: { boardId: 'B2' } })
    expect(leaves.map((leaf) => leaf.route)).toContainEqual({ name: 'statuses', params: { workflowId: 'flow-b' } })
    expect(leaves.map((leaf) => leaf.route)).toContainEqual({ name: 'profiles', params: { teamId: 'design' } })
    expect(leaves.every((leaf) => typeof leaf.to === 'string' && leaf.to.startsWith('/'))).toBe(true)
  })
})

describe('isActiveLeaf', () => {
  const navigation = sidebarNavigation(store, 'ws')

  it('destaca somente o board atual quando ha dois boards', () => {
    const current = railSections(navigation).flat().filter((leaf) => isActiveLeaf(leaf, routeOf('board', { boardId: 'KANB' })))
    expect(current.map((leaf) => leaf.railTitle)).toEqual(['Kanban'])
  })

  it('destaca somente o workflow atual quando ha dois workflows', () => {
    const current = railSections(navigation)
      .flat()
      .filter((leaf) => isActiveLeaf(leaf, routeOf('statuses', { workflowId: 'flow-b' })))
    expect(current.map((leaf) => leaf.railTitle)).toEqual(['Flow B Statuses'])
  })

  it('destaca somente o time atual quando ha dois times', () => {
    const current = railSections(navigation)
      .flat()
      .filter((leaf) => isActiveLeaf(leaf, routeOf('roles', { teamId: 'design' })))
    expect(current.map((leaf) => leaf.railTitle)).toEqual(['Design Roles'])
  })

  it('nao destaca nenhum board na rota /boards sem board escolhido', () => {
    const current = railSections(navigation).flat().filter((leaf) => isActiveLeaf(leaf, routeOf('board', { boardId: '' }), noChosenBoard))
    expect(current).toEqual([])
  })

  it('destaca o board escolhido pela loja quando a rota /boards nao traz id', () => {
    const current = railSections(navigation)
      .flat()
      .filter((leaf) => isActiveLeaf(leaf, routeOf('board', { boardId: '' }), { id: 'KANB', path: '/boards/KANB' }))
    expect(current.map((leaf) => leaf.railTitle)).toEqual(['Kanban'])
  })

  it('nao destaca nada em rota fora das folhas do workspace', () => {
    const current = railSections(navigation).flat().filter((leaf) => isActiveLeaf(leaf, routeOf('profile', {})))
    expect(current).toEqual([])
  })
})
