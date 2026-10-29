import { createRouter, createWebHashHistory } from 'vue-router'
import BoardPage from './views/BoardPage.vue'
import ColumnsPage from './views/ColumnsPage.vue'
import StatusesPage from './views/StatusesPage.vue'
import TransitionsPage from './views/TransitionsPage.vue'
import IssueTypesPage from './views/IssueTypesPage.vue'
import AgentsPage from './views/AgentsPage.vue'
import RolesPage from './views/RolesPage.vue'
import ProfilesPage from './views/ProfilesPage.vue'
import ProfilePage from './views/ProfilePage.vue'
import SecretsPage from './views/SecretsPage.vue'

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', redirect: '/boards' },
    { path: '/boards/:boardId?', name: 'board', component: BoardPage, meta: { title: 'Board', feature: 'board' } },
    { path: '/boards/:boardId/columns', name: 'columns', component: ColumnsPage, meta: { title: 'Columns', feature: null } },
    { path: '/workflows/:workflowId/statuses', name: 'statuses', component: StatusesPage, meta: { title: 'Statuses', feature: 'statuses' } },
    { path: '/workflows/:workflowId/transitions', name: 'transitions', component: TransitionsPage, meta: { title: 'Transitions', feature: 'transitions' } },
    { path: '/workflows/:workflowId/issue-types', name: 'issue-types', component: IssueTypesPage, meta: { title: 'Issue types', feature: 'issueTypes' } },
    { path: '/teams/:teamId/agents', name: 'agents', component: AgentsPage, meta: { title: 'Agents', feature: 'agents' } },
    { path: '/teams/:teamId/roles', name: 'roles', component: RolesPage, meta: { title: 'Roles', feature: 'roles' } },
    { path: '/teams/:teamId/profiles', name: 'profiles', component: ProfilesPage, meta: { title: 'Profiles', feature: 'profiles' } },
    { path: '/profile', name: 'profile', component: ProfilePage, meta: { title: 'Profile', feature: 'profile' } },
    { path: '/secrets', name: 'secrets', component: SecretsPage, meta: { title: 'Secrets', feature: null } },
    { path: '/:pathMatch(.*)*', redirect: '/boards' },
  ],
})
