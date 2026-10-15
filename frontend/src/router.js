import { createRouter, createWebHashHistory } from 'vue-router'
import BoardPage from './views/BoardPage.vue'
import StatusesPage from './views/StatusesPage.vue'
import TransitionsPage from './views/TransitionsPage.vue'
import IssueTypesPage from './views/IssueTypesPage.vue'
import AgentsPage from './views/AgentsPage.vue'
import RolesPage from './views/RolesPage.vue'
import ProfilesPage from './views/ProfilesPage.vue'
import ExperiencesPage from './views/ExperiencesPage.vue'
import ProfilePage from './views/ProfilePage.vue'

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', redirect: '/boards' },
    { path: '/boards/:boardId?', name: 'board', component: BoardPage, meta: { title: 'Board', feature: 'board' } },
    { path: '/workflows/:workflowId/statuses', name: 'statuses', component: StatusesPage, meta: { title: 'Statuses', feature: 'statuses' } },
    { path: '/workflows/:workflowId/transitions', name: 'transitions', component: TransitionsPage, meta: { title: 'Transitions', feature: 'transitions' } },
    { path: '/workflows/:workflowId/issue-types', name: 'issue-types', component: IssueTypesPage, meta: { title: 'Issue types', feature: 'issueTypes' } },
    { path: '/teams/:teamId/agents', name: 'agents', component: AgentsPage, meta: { title: 'Agents', feature: 'agents' } },
    { path: '/teams/:teamId/roles', name: 'roles', component: RolesPage, meta: { title: 'Roles', feature: 'roles' } },
    { path: '/teams/:teamId/profiles', name: 'profiles', component: ProfilesPage, meta: { title: 'Profiles', feature: 'profiles' } },
    { path: '/profile', name: 'profile', component: ProfilePage, meta: { title: 'Profile', feature: 'profile' } },
    { path: '/experiences', name: 'experiences', component: ExperiencesPage, meta: { title: 'Experiences', feature: 'experiences' } },
    { path: '/profile', name: 'profile', component: ProfilePage, meta: { title: 'Profile' } },
    { path: '/:pathMatch(.*)*', redirect: '/boards' },
  ],
})
