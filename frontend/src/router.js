import { createRouter, createWebHashHistory } from 'vue-router'
import BoardPage from './views/BoardPage.vue'
import ColumnsPage from './views/ColumnsPage.vue'
import StatusesPage from './views/StatusesPage.vue'
import TransitionsPage from './views/TransitionsPage.vue'
import IssueTypesPage from './views/IssueTypesPage.vue'
import AgentsPage from './views/AgentsPage.vue'
import RolesPage from './views/RolesPage.vue'
import ProfilesPage from './views/ProfilesPage.vue'
import ExperiencesPage from './views/ExperiencesPage.vue'

export const boardRoutes = [
  { path: '/boards/columns', name: 'columns', component: ColumnsPage, meta: { title: 'Columns', feature: 'columns' } },
  { path: '/boards/statuses', name: 'statuses', component: StatusesPage, meta: { title: 'Statuses', feature: 'statuses' } },
  { path: '/boards/transitions', name: 'transitions', component: TransitionsPage, meta: { title: 'Transitions', feature: 'transitions' } },
  { path: '/boards/issues', name: 'issues', component: IssueTypesPage, meta: { title: 'Issue types', feature: 'issueTypes' } },
]

export const agentRoutes = [
  { path: '/agents', name: 'agents', component: AgentsPage, meta: { title: 'Agents', feature: 'agents' } },
  { path: '/agents/roles', name: 'roles', component: RolesPage, meta: { title: 'Roles', feature: 'roles' } },
  { path: '/agents/profiles', name: 'profiles', component: ProfilesPage, meta: { title: 'Profiles', feature: 'profiles' } },
  { path: '/agents/experiences', name: 'experiences', component: ExperiencesPage, meta: { title: 'Experiences', feature: 'experiences' } },
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', redirect: '/board' },
    { path: '/board', name: 'board', component: BoardPage, meta: { title: 'Board', feature: 'board' } },
    ...boardRoutes,
    ...agentRoutes,
    { path: '/:pathMatch(.*)*', redirect: '/board' },
  ],
})
