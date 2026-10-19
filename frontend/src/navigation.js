const WORKFLOW_PAGES = [
  { key: 'statuses', label: 'Statuses', icon: 'statuses', feature: 'statuses', route: 'statuses', path: (id) => `/workflows/${id}/statuses` },
  { key: 'transitions', label: 'Transitions', icon: 'transitions', feature: 'transitions', route: 'transitions', path: (id) => `/workflows/${id}/transitions` },
  { key: 'issue-types', label: 'Issue types', icon: 'issues', feature: 'issueTypes', route: 'issue-types', path: (id) => `/workflows/${id}/issue-types` },
]

const TEAM_PAGES = [
  { key: 'agents', label: 'Agents', icon: 'agents', feature: 'agents', route: 'agents', path: (id) => `/teams/${id}/agents` },
  { key: 'roles', label: 'Roles', icon: 'roles', feature: 'roles', route: 'roles', path: (id) => `/teams/${id}/roles` },
  { key: 'profiles', label: 'Profiles', icon: 'profiles', feature: 'profiles', route: 'profiles', path: (id) => `/teams/${id}/profiles` },
]

function pageLeaf(page, parent, section) {
  return {
    key: `${section}:${parent.id}:${page.key}`,
    label: page.label,
    title: page.label,
    railTitle: `${parent.name} · ${page.label}`,
    icon: page.icon,
    to: page.path(parent.id),
    route: { name: page.route, params: { [`${section}Id`]: parent.id } },
    feature: page.feature,
  }
}

function pageLeaves(pages, parent, section) {
  return pages.map((page) => pageLeaf(page, parent, section))
}

function boardLeaf(board) {
  return {
    key: `board:${board.id}`,
    label: board.name,
    title: board.name,
    railTitle: board.name,
    icon: 'board',
    to: `/boards/${board.id}`,
    route: { name: 'board', params: { boardId: board.id } },
    feature: null,
  }
}

function experienceEntry() {
  return {
    entity: { id: 'experiences', name: 'Experiences' },
    leaves: [
      {
        key: 'experience:experiences',
        label: 'Experiences',
        title: 'Experiences',
        railTitle: 'Experiences',
        icon: 'experiences',
        to: '/experiences',
        route: { name: 'experiences', params: {} },
        feature: 'experiences',
      },
    ],
  }
}

function emptyNavigation() {
  return { boards: [], workflows: [], teams: [], experiences: [] }
}

function sectionsOf(navigation) {
  return [...navigation.boards, ...navigation.workflows, ...navigation.teams, ...navigation.experiences].map((entry) => entry.leaves)
}

export function sidebarNavigation(store, workspaceId) {
  if (!workspaceId) {
    return emptyNavigation()
  }
  const boards = store.boardsOf(workspaceId).map((board) => ({ entity: board, leaves: [boardLeaf(board)] }))
  const workflows = store.workflowsOf(workspaceId).map((workflow) => ({ entity: workflow, leaves: pageLeaves(WORKFLOW_PAGES, workflow, 'workflow') }))
  const teams = store.teamsOf(workspaceId).map((team) => ({ entity: team, leaves: pageLeaves(TEAM_PAGES, team, 'team') }))
  return { boards, workflows, teams, experiences: [experienceEntry()] }
}

export function railSections(navigation) {
  return sectionsOf(navigation).filter((leaves) => leaves.length > 0)
}

export function isActiveLeaf(leaf, route, chosen) {
  const sameRoute = leaf.route.name === route.name
  const boardChosen = route.name === 'board' && !route.params.boardId
  if (!sameRoute) {
    return false
  }
  if (boardChosen) {
    return Boolean(chosen) && chosen.id === leaf.route.params.boardId
  }
  return matchesParams(leaf, route)
}

function matchesParams(leaf, route) {
  return Object.entries(leaf.route.params).every(([key, value]) => route.params[key] === value)
}
