import { VIVY_PORTRAIT } from './seed-portrait.js'

const now = Date.now()
const hours = (count) => count * 60 * 60 * 1000

const legacyDescription = `LEGACY-DESCRIPTION-START\n${'D'.repeat(4200)}\nLEGACY-DESCRIPTION-END`
const legacyComment = `LEGACY-COMMENT-START\n${'C'.repeat(1100)}\nLEGACY-COMMENT-END`

export function createSeed() {
  return {
    workspaces: [{ id: 'default', name: 'Default' }],

    workflows: [{ id: 'standard', name: 'Standard', workspaceId: 'default' }],

    statuses: [
      { id: 'to-develop', workflowId: 'standard', name: 'To Develop', type: 'initial', color: '#4c9aff', roleIds: ['product-owner'] },
      { id: 'in-development', workflowId: 'standard', name: 'In Development', type: 'transitional', color: '#ff991f', roleIds: ['coder'] },
      { id: 'in-review', workflowId: 'standard', name: 'In Review', type: 'transitional', color: '#8777d9', roleIds: ['tech-lead'] },
      { id: 'in-test', workflowId: 'standard', name: 'In Test', type: 'transitional', color: '#00b8d9', roleIds: ['quality-assurance'] },
      { id: 'done', workflowId: 'standard', name: 'Done', type: 'final', color: '#57d9a3', roleIds: [] },
      { id: 'canceled', workflowId: 'standard', name: 'Canceled', type: 'final', color: '#97a0af', roleIds: [] },
    ],

    transitions: [
      { id: 't1', workflowId: 'standard', from: 'to-develop', to: 'in-development', type: 'both' },
      { id: 't2', workflowId: 'standard', from: 'in-development', to: 'in-review', type: 'both' },
      { id: 't3', workflowId: 'standard', from: 'in-review', to: 'in-test', type: 'both' },
      { id: 't4', workflowId: 'standard', from: 'in-test', to: 'done', type: 'both' },
      { id: 't5', workflowId: 'standard', from: '*', to: 'canceled', type: 'user' },
    ],

    issueTypes: [
      {
        id: 'task',
        workflowId: 'standard',
        name: 'Task',
        level: 1,
        fields: [
          { key: 'summary', label: 'Summary', type: 'text', required: true, system: true },
          { key: 'description', label: 'Description', type: 'textarea', required: true, system: true },
          { key: 'priority', label: 'Priority', type: 'select', required: false, options: ['Low', 'Medium', 'High'] },
        ],
      },
    ],

    boards: [
      {
        id: 'KANB',
        name: 'Kanban',
        workspaceId: 'default',
        workflowId: 'standard',
        filters: { statusIds: [] },
        columns: [
          { id: 'todo', name: 'Todo', wip: null, statusIds: ['to-develop'] },
          { id: 'doing', name: 'Doing', wip: 6, statusIds: ['in-development', 'in-review', 'in-test'] },
          { id: 'done', name: 'Done', wip: null, statusIds: ['done', 'canceled'] },
        ],
      },
    ],

    teams: [{ id: 'default', name: 'Default team', workspaceId: 'default' }],

    roles: [
      { id: 'product-owner', teamId: 'default', name: 'Product Owner', description: 'Turns intent into a framed problem.', unblock: false, interactive: true },
      { id: 'tech-lead', teamId: 'default', name: 'Tech Lead', description: 'Reviews changes and unblocks impediments.', unblock: true, interactive: false },
      { id: 'coder', teamId: 'default', name: 'Coder', description: 'Implements the planned work.', unblock: false, interactive: false },
      { id: 'quality-assurance', teamId: 'default', name: 'Quality Assurance', description: 'Refines acceptance and verifies outcomes.', unblock: false, interactive: false },
    ],

    profiles: [
      { id: 'socratic', teamId: 'default', name: 'Socratic', style: 'Deconstructs certainties and hunts the root cause.', tone: 'Inquisitive', emojiUsage: 'Never' },
      { id: 'nerd', teamId: 'default', name: 'Nerd', style: 'Deep reasoning, lists and a rigorous process.', tone: 'Precise', emojiUsage: 'Moderate' },
      { id: 'professor', teamId: 'default', name: 'Professor', style: 'A demanding master who reviews and teaches.', tone: 'Didactic', emojiUsage: 'Never' },
      { id: 'firebrand', teamId: 'default', name: 'Firebrand', style: 'Energy, urgency and intuition-driven decisions.', tone: 'Intense', emojiUsage: 'Frequent' },
      { id: 'gallant', teamId: 'default', name: 'Gallant', style: 'Warmth, courtesy and care for people.', tone: 'Warm', emojiUsage: 'Moderate' },
      { id: 'skeptic', teamId: 'default', name: 'Skeptic', style: 'Constant doubt and honest investigation.', tone: 'Cautious', emojiUsage: 'Never' },
      { id: 'goth', teamId: 'default', name: 'Goth', style: 'Mystery, dark charm and geek culture.', tone: 'Deadpan', emojiUsage: 'Never' },
      { id: 'pacifist', teamId: 'default', name: 'Pacifist', style: 'Harmony, consensus and active listening.', tone: 'Calm', emojiUsage: 'Moderate' },
      { id: 'activist', teamId: 'default', name: 'Activist', style: 'Contagious joy and fervor for causes.', tone: 'Energetic', emojiUsage: 'Frequent' },
    ],

    agents: [
      { id: 'vivy', teamId: 'default', name: 'Vivy', whoAmI: 'The first autonomous agent, curious about people.', age: 20, maritalStatus: 'Single', roleIds: ['product-owner'], profileId: 'socratic', avatarColor: '#4c9aff', portrait: VIVY_PORTRAIT },
      { id: 'kaio', teamId: 'default', name: 'Kaio', whoAmI: 'A pragmatic builder.', age: 27, maritalStatus: 'Single', roleIds: ['coder'], profileId: 'skeptic', avatarColor: '#ff991f' },
      { id: 'alan', teamId: 'default', name: 'Alan', whoAmI: 'A patient reviewer and teacher.', age: 41, maritalStatus: 'Married', roleIds: ['tech-lead'], profileId: 'professor', avatarColor: '#8777d9' },
      { id: 'mai', teamId: 'default', name: 'Mai', whoAmI: 'Distrusts the happy path.', age: 33, maritalStatus: 'Single', roleIds: ['quality-assurance'], profileId: 'activist', avatarColor: '#00b8d9' },
    ],

    experiences: [
      { id: 'vivy-origin', agentId: 'vivy', title: 'First boot', body: 'Woke up to a board full of unanswered intents and decided to help.', at: new Date(now - hours(30)).toISOString() },
    ],

    sources: [],

    user: { login: 'admin', displayName: '' },

    issues: [
      {
        id: 'KANB-1', boardId: 'KANB', typeId: 'task', summary: 'Frame the onboarding intent',
        description: 'The client wants a smoother onboarding. Clarify value, risks and open questions.',
        statusId: 'to-develop', assigneeId: 'vivy', reporter: 'user', worker: '', order: 0,
        flags: [], comments: [
          { id: 'c1', author: 'user', body: 'We lose users in the first step.', at: new Date(now - hours(26)).toISOString() },
        ],
        createdAt: new Date(now - hours(26)).toISOString(), updatedAt: new Date(now - hours(26)).toISOString(),
      },
      {
        id: 'KANB-2', boardId: 'KANB', typeId: 'task', summary: 'Fix the login redirect loop',
        description: 'After a successful login the app bounces back to the sign-in screen on slow networks.',
        statusId: 'in-development', assigneeId: 'kaio', reporter: 'user', worker: 'Kaio', order: 0,
        flags: [], comments: [],
        createdAt: new Date(now - hours(10)).toISOString(), updatedAt: new Date(now - hours(2)).toISOString(),
      },
      {
        id: 'KANB-3', boardId: 'KANB', typeId: 'task', summary: 'Add dark theme to the board',
        description: 'Dark theme by default with a light option.',
        statusId: 'in-review', assigneeId: 'alan', reporter: 'user', worker: 'Alan', order: 0,
        flags: [], comments: [],
        createdAt: new Date(now - hours(8)).toISOString(), updatedAt: new Date(now - hours(1)).toISOString(),
      },
      {
        id: 'KANB-4', boardId: 'KANB', typeId: 'task', summary: 'Card shows aging flag',
        description: 'Flag cards with more than 24h without activity.',
        statusId: 'in-test', assigneeId: 'mai', reporter: 'user', worker: '', order: 0,
        flags: [], comments: [],
        createdAt: new Date(now - hours(40)).toISOString(), updatedAt: new Date(now - hours(30)).toISOString(),
      },
      {
        id: 'KANB-5', boardId: 'KANB', typeId: 'task', summary: 'Set up the CI pipeline',
        description: 'Run tests on every pull request.',
        statusId: 'done', assigneeId: 'kaio', reporter: 'user', worker: '', order: 0,
        flags: [], comments: [],
        createdAt: new Date(now - hours(80)).toISOString(), updatedAt: new Date(now - hours(50)).toISOString(),
      },
      {
        id: 'KANB-6', boardId: 'KANB', typeId: 'task', summary: 'Retire the legacy exporter',
        description: 'No longer needed.',
        statusId: 'canceled', assigneeId: null, reporter: 'user', worker: '', order: 0,
        flags: [], comments: [],
        createdAt: new Date(now - hours(120)).toISOString(), updatedAt: new Date(now - hours(100)).toISOString(),
      },
      {
        id: 'KANB-7', boardId: 'KANB', typeId: 'task', summary: 'Legacy issue over the limits',
        description: legacyDescription,
        statusId: 'to-develop', assigneeId: 'vivy', reporter: 'user', worker: '', order: 1,
        flags: [], history: [], comments: [
          { id: 'c-legacy', author: 'user', body: legacyComment, at: new Date(now - hours(25)).toISOString() },
        ],
        createdAt: new Date(now - hours(25)).toISOString(), updatedAt: new Date(now - hours(25)).toISOString(),
      },
    ],
  }
}
