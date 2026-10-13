<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'
import EntityModal from './EntityModal.vue'
import BoardEditor from './BoardEditor.vue'
import { boardStore } from '../store/board.js'
import { isSimulated } from '../api/index.js'

defineProps({ open: { type: Boolean, default: false } })

const route = useRoute()
const router = useRouter()

const openBoards = ref(true)
const openWorkflows = ref(true)
const openTeams = ref(true)
const openSources = ref(true)
const editor = ref(null)

const workspace = computed(() => boardStore.workspace.value)
const workspaceId = computed(() => workspace.value?.id)
const boards = computed(() => (workspaceId.value ? boardStore.boardsOf(workspaceId.value) : []))
const workflows = computed(() => (workspaceId.value ? boardStore.workflowsOf(workspaceId.value) : []))
const teams = computed(() => (workspaceId.value ? boardStore.teamsOf(workspaceId.value) : []))
const sources = computed(() => (workspaceId.value ? boardStore.sourcesOf(workspaceId.value) : []))

const slugify = (text) =>
  String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

function open(kind, draft, title, fields) {
  editor.value = { kind, draft, title, fields, error: '' }
}

function close() {
  editor.value = null
}

/* Editors */
function editWorkspace() {
  open('workspace', { ...workspace.value }, 'Edit workspace', [{ key: 'name', label: 'Name' }])
}

function addBoard() {
  open('board', { id: '', name: '', workflowId: workflows.value[0]?.id || '', workspaceId: workspaceId.value, filters: { statusIds: [] }, columns: [] }, 'New board', [])
}

function editBoard(board) {
  open('board', JSON.parse(JSON.stringify({ ...board, __existing: true })), 'Edit board', [])
}

function addWorkflow() {
  open(
    'workflow',
    { id: '', name: '', workspaceId: workspaceId.value, templateId: '' },
    'New workflow',
    [
      { key: 'id', label: 'ID', hint: 'letters, digits or dashes' },
      { key: 'name', label: 'Name' },
      {
        key: 'templateId',
        label: 'Start from a template (optional)',
        type: 'select',
        options: () => [{ value: '', label: 'Empty' }, ...workflows.value.map((workflow) => ({ value: workflow.id, label: workflow.name }))],
      },
    ],
  )
}

function editWorkflow(workflow) {
  open('workflow', { ...workflow, __existing: true }, 'Edit workflow', [
    { key: 'name', label: 'Name' },
    { key: 'id', label: 'ID' },
  ])
}

function addTeam() {
  open('team', { id: '', name: '', workspaceId: workspaceId.value }, 'New team', [
    { key: 'name', label: 'Name' },
    { key: 'id', label: 'ID (optional)' },
  ])
}

function editTeam(team) {
  open('team', { ...team, __existing: true }, 'Edit team', [{ key: 'name', label: 'Name' }])
}

function addSource() {
  open('source', { id: '', name: '', path: '/workspace', workspaceId: workspaceId.value }, 'New source', [
    { key: 'name', label: 'Name' },
    { key: 'path', label: 'Directory', hint: 'where the agents can work' },
  ])
}

function editSource(source) {
  open('source', { ...source, __existing: true }, 'Edit source', [
    { key: 'name', label: 'Name' },
    { key: 'path', label: 'Directory' },
  ])
}

function normalizeBoard(board) {
  if (!/^[A-Za-z]{3,6}$/.test(board.id)) {
    throw new Error('Board ID must be 3 to 6 letters')
  }
  if (!board.name) {
    throw new Error('Name is required')
  }
  board.columns = (board.columns || []).map((column) => ({
    id: column.id || slugify(column.name),
    name: column.name,
    wip: column.wip ?? null,
    statusIds: column.statusIds || [],
  }))
  board.filters = board.filters || { statusIds: [] }
}

async function copyWorkflow(templateId, workflow) {
  const idMap = {}
  for (const status of boardStore.statusesOf(templateId)) {
    const newId = `${workflow.id}-${status.id}`
    idMap[status.id] = newId
    await boardStore.saveEntity('statuses', { workflowId: workflow.id, id: newId, name: status.name, type: status.type, color: status.color, roleIds: status.roleIds || [] })
  }
  for (const transition of boardStore.transitionsOf(templateId)) {
    await boardStore.saveEntity('transitions', {
      workflowId: workflow.id,
      from: transition.from === '*' ? '*' : idMap[transition.from],
      to: idMap[transition.to],
      type: transition.type,
    })
  }
  for (const type of boardStore.issueTypesOf(templateId)) {
    await boardStore.saveEntity('issueTypes', { workflowId: workflow.id, id: `${workflow.id}-${type.id}`, name: type.name, level: type.level, fields: type.fields })
  }
}

async function saveEditor() {
  const current = editor.value
  current.error = ''
  try {
    if (current.kind === 'board') {
      normalizeBoard(current.draft)
    }
    if (current.kind === 'workflow') {
      const isNew = !current.draft.__existing
      const templateId = current.draft.templateId
      delete current.draft.__existing
      delete current.draft.templateId
      if (!current.draft.id && current.draft.name) {
        current.draft.id = slugify(current.draft.name)
      }
      const workflow = await boardStore.saveEntity('workflows', current.draft)
      if (isNew && templateId) {
        await copyWorkflow(templateId, workflow)
      }
      editor.value = null
      return
    }
    if (current.kind === 'team' && !current.draft.id && current.draft.name) {
      current.draft.id = slugify(current.draft.name)
    }
    if (current.kind === 'source' && !current.draft.id && current.draft.name) {
      current.draft.id = slugify(current.draft.name)
    }
    delete current.draft.__existing
    await boardStore.saveEntity(collectionOf(current.kind), current.draft)
    editor.value = null
  } catch (problem) {
    current.error = problem.message
  }
}

function collectionOf(kind) {
  return { workspace: 'workspaces', board: 'boards', workflow: 'workflows', team: 'teams', source: 'sources' }[kind]
}

async function removeEntity(kind, id, label) {
  if (!window.confirm(`Delete "${label}"?`)) {
    return
  }
  if (kind === 'workflow') {
    for (const status of boardStore.statusesOf(id)) await boardStore.removeEntity('statuses', status.id)
    for (const transition of boardStore.transitionsOf(id)) await boardStore.removeEntity('transitions', transition.id)
    for (const type of boardStore.issueTypesOf(id)) await boardStore.removeEntity('issueTypes', type.id)
  }
  if (kind === 'team') {
    for (const agent of boardStore.agentsOf(id)) await boardStore.removeEntity('agents', agent.id)
    for (const role of boardStore.rolesOf(id)) await boardStore.removeEntity('roles', role.id)
    for (const profile of boardStore.profilesOf(id)) await boardStore.removeEntity('profiles', profile.id)
  }
  await boardStore.removeEntity(collectionOf(kind), id)
}
</script>

<template>
  <aside class="sidebar" :class="{ open }">
    <div class="brand">
      <span class="brand-icon"><AppIcon name="smartboard" size="18" /></span>
      <span>Multi Agent</span>
    </div>

    <nav class="nav">
      <div class="nav-entry" title="Workspaces">
        <AppIcon name="workspace" size="15" />
        <span>Workspaces</span>
        <span class="entry-actions">
          <button class="icon-btn" title="New workspace (coming soon)" disabled><AppIcon name="plus" size="14" /></button>
        </span>
      </div>

      <div class="submenu">
        <div class="nav-entry" v-if="workspace">
          <AppIcon name="workspace" size="15" />
          <span>{{ workspace.name }}</span>
          <span class="entry-actions">
            <button class="icon-btn" title="Edit workspace" @click.stop="editWorkspace"><AppIcon name="edit" size="14" /></button>
          </span>
        </div>

        <div class="submenu">
          <!-- Boards -->
          <div class="nav-entry" :class="{ open: openBoards }" @click="openBoards = !openBoards">
            <AppIcon name="board" size="15" />
            <span>Boards</span>
            <AppIcon class="chevron" name="chevron" size="13" />
            <span class="entry-actions">
              <button class="icon-btn" title="New board" @click.stop="addBoard"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openBoards" class="submenu">
            <div v-for="board in boards" :key="board.id" class="nav-entry">
              <RouterLink class="leaf" :to="`/boards/${board.id}`">
                <AppIcon name="board" size="14" />
                <span class="label">{{ board.name }}</span>
                <span class="badge-id">{{ board.id }}</span>
              </RouterLink>
              <span class="entry-actions">
                <button class="icon-btn" title="Edit board" @click.stop="editBoard(board)"><AppIcon name="edit" size="14" /></button>
              </span>
            </div>
          </div>

          <!-- Workflows -->
          <div class="nav-entry" :class="{ open: openWorkflows }" @click="openWorkflows = !openWorkflows">
            <AppIcon name="layers" size="15" />
            <span>Workflows</span>
            <AppIcon class="chevron" name="chevron" size="13" />
            <span class="entry-actions">
              <button class="icon-btn" title="New workflow" @click.stop="addWorkflow"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openWorkflows" class="submenu">
            <div v-for="workflow in workflows" :key="workflow.id">
              <div class="nav-entry">
                <AppIcon name="layers" size="14" />
                <span>{{ workflow.name }}</span>
                <span class="entry-actions">
                  <button class="icon-btn" title="Edit workflow" @click.stop="editWorkflow(workflow)"><AppIcon name="edit" size="14" /></button>
                </span>
              </div>
              <div class="submenu">
                <RouterLink class="leaf" :to="`/workflows/${workflow.id}/statuses`"><AppIcon name="statuses" size="14" /><span class="label">Statuses</span><span class="sim-dot sm" v-if="isSimulated('statuses')"></span></RouterLink>
                <RouterLink class="leaf" :to="`/workflows/${workflow.id}/transitions`"><AppIcon name="transitions" size="14" /><span class="label">Transitions</span><span class="sim-dot sm" v-if="isSimulated('transitions')"></span></RouterLink>
                <RouterLink class="leaf" :to="`/workflows/${workflow.id}/issue-types`"><AppIcon name="issues" size="14" /><span class="label">Issue types</span><span class="sim-dot sm" v-if="isSimulated('issueTypes')"></span></RouterLink>
              </div>
            </div>
          </div>

          <!-- Teams -->
          <div class="nav-entry" :class="{ open: openTeams }" @click="openTeams = !openTeams">
            <AppIcon name="agents" size="15" />
            <span>Teams</span>
            <AppIcon class="chevron" name="chevron" size="13" />
            <span class="entry-actions">
              <button class="icon-btn" title="New team" @click.stop="addTeam"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openTeams" class="submenu">
            <div v-for="team in teams" :key="team.id">
              <div class="nav-entry">
                <AppIcon name="agents" size="14" />
                <span>{{ team.name }}</span>
                <span class="entry-actions">
                  <button class="icon-btn" title="Edit team" @click.stop="editTeam(team)"><AppIcon name="edit" size="14" /></button>
                </span>
              </div>
              <div class="submenu">
                <RouterLink class="leaf" :to="`/teams/${team.id}/agents`"><AppIcon name="agents" size="14" /><span class="label">Agents</span><span class="sim-dot sm" v-if="isSimulated('agents')"></span></RouterLink>
                <RouterLink class="leaf" :to="`/teams/${team.id}/roles`"><AppIcon name="roles" size="14" /><span class="label">Roles</span><span class="sim-dot sm" v-if="isSimulated('roles')"></span></RouterLink>
                <RouterLink class="leaf" :to="`/teams/${team.id}/profiles`"><AppIcon name="profiles" size="14" /><span class="label">Profiles</span><span class="sim-dot sm" v-if="isSimulated('profiles')"></span></RouterLink>
              </div>
            </div>
          </div>

          <!-- Sources -->
          <div class="nav-entry" :class="{ open: openSources }" @click="openSources = !openSources">
            <AppIcon name="folder" size="15" />
            <span>Sources</span>
            <AppIcon class="chevron" name="chevron" size="13" />
            <span class="entry-actions">
              <button class="icon-btn" title="New source" @click.stop="addSource"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openSources" class="submenu">
            <div v-for="source in sources" :key="source.id" class="nav-entry">
              <AppIcon name="folder" size="14" />
              <span class="label" :title="source.path">{{ source.name }}</span>
              <span class="entry-actions">
                <button class="icon-btn" title="Edit source" @click.stop="editSource(source)"><AppIcon name="edit" size="14" /></button>
                <button class="icon-btn" title="Delete source" @click.stop="removeEntity('source', source.id, source.name)"><AppIcon name="close" size="14" /></button>
              </span>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <EntityModal
      v-if="editor && editor.kind !== 'board'"
      :title="editor.title"
      :fields="editor.fields"
      :draft="editor.draft"
      :error="editor.error"
      @save="saveEditor"
      @close="close"
    />
    <BoardEditor
      v-else-if="editor"
      :draft="editor.draft"
      :error="editor.error"
      @save="saveEditor"
      @close="close"
    />
  </aside>
</template>
