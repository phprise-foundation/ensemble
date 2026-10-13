import { createRouter, createWebHashHistory } from 'vue-router'
import BoardPage from './views/BoardPage.vue'
import PlaceholderPage from './views/PlaceholderPage.vue'

export const boardRoutes = [
  { path: '/boards/columns', name: 'columns', component: PlaceholderPage, meta: { title: 'Columns', feature: 'columns' } },
  { path: '/boards/statuses', name: 'statuses', component: PlaceholderPage, meta: { title: 'Statuses', feature: 'statuses' } },
  { path: '/boards/transitions', name: 'transitions', component: PlaceholderPage, meta: { title: 'Transitions', feature: 'transitions' } },
  { path: '/boards/issues', name: 'issues', component: PlaceholderPage, meta: { title: 'Issue types', feature: 'issueTypes' } },
]

export const agentRoutes = [
  { path: '/agents/roles', name: 'roles', component: PlaceholderPage, meta: { title: 'Roles', feature: 'roles' } },
  { path: '/agents/profiles', name: 'profiles', component: PlaceholderPage, meta: { title: 'Profiles', feature: 'profiles' } },
  { path: '/agents/experiences', name: 'experiences', component: PlaceholderPage, meta: { title: 'Experiences', feature: 'experiences' } },
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
