<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, Teleport, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'
import EntityModal from './EntityModal.vue'
import BoardEditor from './BoardEditor.vue'
import { boardStore } from '../store/board.js'
import { isSimulated } from '../api/index.js'
import { isActiveLeaf, sidebarNavigation, railSections } from '../navigation.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  isMobile: { type: Boolean, default: false },
  narrow: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const route = useRoute()
const router = useRouter()
const sidebar = ref(null)

const isModal = computed(() => props.isMobile && props.open)

router.afterEach(() => {
  if (props.open) {
    emit('close')
  }
})

function focusableElements() {
  if (!sidebar.value) {
    return []
  }
  return Array.from(
    sidebar.value.querySelectorAll(
      'button:not([disabled]), [href]:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  ).filter((element) => {
    if (element.hidden || !element.isConnected) {
      return false
    }
    const style = window.getComputedStyle(element)
    return style.display !== 'none' && style.visibility !== 'hidden'
  })
}

function focusTrap(event) {
  if (event.key !== 'Tab') {
    return
  }
  const elements = focusableElements()
  if (elements.length === 0) {
    return
  }
  const first = elements[0]
  const last = elements[elements.length - 1]
  const movingBack = event.shiftKey
  const atStart = document.activeElement === first
  const atEnd = document.activeElement === last
  if (movingBack && atStart) {
    event.preventDefault()
    last.focus()
    return
  }
  if (!movingBack && atEnd) {
    event.preventDefault()
    first.focus()
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    emit('close')
    return
  }
  focusTrap(event)
}

function attachKeydown() {
  document.addEventListener('keydown', handleKeydown)
}

function detachKeydown() {
  document.removeEventListener('keydown', handleKeydown)
}

function focusFirst() {
  const first = focusableElements()[0]
  first?.focus()
}

function activateTrap() {
  attachKeydown()
  nextTick(focusFirst)
}

watch(
  () => props.open,
  (isOpen, wasOpen) => {
    if (props.isMobile && isOpen) {
      activateTrap()
      return
    }
    if (wasOpen) {
      detachKeydown()
    }
  }
)

watch(
  () => props.isMobile,
  (mobile) => {
    if (!mobile) {
      detachKeydown()
      return
    }
    if (props.open) {
      activateTrap()
    }
  }
)

onMounted(() => {
  if (props.isMobile && props.open) {
    activateTrap()
  }
})

onUnmounted(() => {
  detachKeydown()
})

const openBoards = ref(true)
const openWorkflows = ref(true)
const openTeams = ref(true)
const openSources = ref(true)
const editor = ref(null)

const workspace = computed(() => boardStore.workspace.value)
const workspaceId = computed(() => workspace.value?.id)
const navigation = computed(() => sidebarNavigation(boardStore, workspaceId.value))
const railGroups = computed(() => railSections(navigation.value))
const railLeaves = computed(() =>
  railGroups.value.map((leaves) => leaves.map((leaf) => ({ ...leaf, current: isActiveLeaf(leaf, route, boardStore.board.value) }))),
)
const workflows = computed(() => navigation.value.workflows.map((entry) => entry.entity))
const showRail = computed(() => Boolean(props.collapsed && !props.isMobile))
const sources = computed(() => (workspaceId.value ? boardStore.sourcesOf(workspaceId.value) : []))

const slugify = (text) =>
  String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

function openEditor(kind, draft, title, fields) {
  emit('close')
  editor.value = { kind, draft, title, fields, error: '' }
}

function close() {
  editor.value = null
}

/* Editors */
function editWorkspace() {
  openEditor('workspace', { ...workspace.value }, 'Edit workspace', [{ key: 'name', label: 'Name' }])
}

function addBoard() {
  openEditor('board', { id: '', name: '', workflowId: workflows.value[0]?.id || '', workspaceId: workspaceId.value, filters: { statusIds: [] }, columns: [] }, 'New board', [])
}

function editBoard(board) {
  openEditor('board', JSON.parse(JSON.stringify({ ...board, __existing: true })), 'Edit board', [])
}

function addWorkflow() {
  openEditor(
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
  openEditor('workflow', { ...workflow, __existing: true }, 'Edit workflow', [
    { key: 'name', label: 'Name' },
  ])
}

function addTeam() {
  openEditor('team', { id: '', name: '', workspaceId: workspaceId.value }, 'New team', [
    { key: 'name', label: 'Name' },
    { key: 'id', label: 'ID (optional)' },
  ])
}

function editTeam(team) {
  openEditor('team', { ...team, __existing: true }, 'Edit team', [{ key: 'name', label: 'Name' }])
}

function addSource() {
  openEditor('source', { id: '', name: '', path: '', workspaceId: workspaceId.value }, 'New source', [
    { key: 'path', label: 'Directory (local path)', hint: 'e.g. /home/you/src/my-project — the worker uses it as workdir' },
    { key: 'name', label: 'Name', hint: 'optional, defaults to the folder name' },
  ])
}

function editSource(source) {
  openEditor('source', { ...source, __existing: true }, 'Edit source', [
    { key: 'path', label: 'Directory (local path)' },
    { key: 'name', label: 'Name' },
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
      delete current.draft.templateId
      if (!isNew && !current.draft.id && current.draft.name) {
        current.draft.id = slugify(current.draft.name)
      }
      const workflow = await boardStore.saveEntity('workflows', current.draft)
      if (isNew && templateId) {
        await copyWorkflow(templateId, workflow)
      }
      editor.value = null
      return
    }
    if (current.kind === 'team' && current.draft.__existing && !current.draft.id && current.draft.name) {
      current.draft.id = slugify(current.draft.name)
    }
    if (current.kind === 'source') {
      if (!current.draft.name && current.draft.path) {
        current.draft.name = current.draft.path.split('/').filter(Boolean).pop() || 'source'
      }
      if (current.draft.__existing && !current.draft.id) {
        current.draft.id = slugify(current.draft.name)
      }
    }
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
  <aside
    id="app-sidebar"
    ref="sidebar"
    class="sidebar"
    :class="{ open, collapsed }"
    :role="isModal ? 'dialog' : undefined"
    :aria-modal="isModal ? 'true' : undefined"
    :aria-hidden="props.isMobile ? !open : undefined"
    aria-label="Menu"
  >
    <div class="brand">
      <span class="brand-icon"><AppIcon name="smartboard" size="18" /></span>
      <span>Multi Agent</span>
      <button
        v-if="narrow && isMobile"
        class="icon-btn sidebar-close"
        type="button"
        title="Fechar menu"
        aria-label="Fechar menu"
        @click="emit('close')"
      >
        <AppIcon name="close" size="14" />
      </button>
    </div>

    <nav v-if="showRail" class="nav rail" aria-label="Pages">
      <template v-for="(leaves, index) in railLeaves" :key="leaves[0].key">
        <div v-if="index > 0" class="rail-divider" role="separator" aria-orientation="vertical"></div>
        <RouterLink v-for="leaf in leaves" :key="leaf.key" class="leaf rail-leaf" :to="leaf.to" :title="leaf.railTitle" :aria-label="leaf.railTitle" :aria-current="leaf.current ? 'page' : undefined">
          <AppIcon :name="leaf.icon" :size="18" />
        </RouterLink>
      </template>
    </nav>

    <nav v-else class="nav">
      <div class="nav-entry" title="Workspaces">
        <AppIcon name="workspace" size="18" />
        <span>Workspaces</span>
        <span class="entry-actions">
          <button class="icon-btn" title="New workspace (coming soon)" aria-label="New workspace (coming soon)" disabled><AppIcon name="plus" size="14" /></button>
        </span>
      </div>

      <div class="submenu">
        <div class="nav-entry" v-if="workspace" :title="workspace?.name">
          <AppIcon name="workspace" size="14" />
          <span>{{ workspace.name }}</span>
          <span class="entry-actions">
            <button class="icon-btn" title="Edit workspace" aria-label="Edit workspace" @click.stop="editWorkspace"><AppIcon name="edit" size="14" /></button>
          </span>
        </div>

        <div class="submenu">
          <!-- Boards -->
          <div class="nav-entry" :class="{ open: openBoards }" title="Boards" @click="openBoards = !openBoards">
            <AppIcon name="board" size="18" />
            <span>Boards</span>
            <AppIcon class="chevron" name="chevron" size="14" />
            <span class="entry-actions">
              <button class="icon-btn" title="New board" aria-label="New board" @click.stop="addBoard"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openBoards" class="submenu">
            <div v-for="entry in navigation.boards" :key="entry.entity.id" class="nav-entry">
              <RouterLink v-for="leaf in entry.leaves" :key="leaf.key" class="leaf" :to="leaf.to" :title="leaf.title">
                <AppIcon :name="leaf.icon" size="14" />
                <span class="label">{{ leaf.label }}</span>
                <span class="badge-id">{{ entry.entity.id }}</span>
              </RouterLink>
              <span class="entry-actions">
                <button class="icon-btn" title="Edit board" aria-label="Edit board" @click.stop="editBoard(entry.entity)"><AppIcon name="edit" size="14" /></button>
              </span>
            </div>
          </div>

          <!-- Workflows -->
          <div class="nav-entry" :class="{ open: openWorkflows }" title="Workflows" @click="openWorkflows = !openWorkflows">
            <AppIcon name="layers" size="18" />
            <span>Workflows</span>
            <AppIcon class="chevron" name="chevron" size="14" />
            <span class="entry-actions">
              <button class="icon-btn" title="New workflow" aria-label="New workflow" @click.stop="addWorkflow"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openWorkflows" class="submenu">
            <div v-for="entry in navigation.workflows" :key="entry.entity.id">
              <div class="nav-entry" :title="entry.entity.name">
                <AppIcon name="layers" size="14" />
                <span>{{ entry.entity.name }}</span>
                <span class="entry-actions">
                  <button class="icon-btn" title="Edit workflow" aria-label="Edit workflow" @click.stop="editWorkflow(entry.entity)"><AppIcon name="edit" size="14" /></button>
                </span>
              </div>
              <div class="submenu">
                <RouterLink v-for="leaf in entry.leaves" :key="leaf.key" class="leaf" :to="leaf.to" :title="leaf.title">
                  <AppIcon :name="leaf.icon" size="14" />
                  <span class="label">{{ leaf.label }}</span>
                  <span class="sim-dot sm" v-if="leaf.feature && isSimulated(leaf.feature)"></span>
                </RouterLink>
              </div>
            </div>
          </div>

          <!-- Teams -->
          <div class="nav-entry" :class="{ open: openTeams }" title="Teams" @click="openTeams = !openTeams">
            <AppIcon name="agents" size="18" />
            <span>Teams</span>
            <AppIcon class="chevron" name="chevron" size="14" />
            <span class="entry-actions">
              <button class="icon-btn" title="New team" aria-label="New team" @click.stop="addTeam"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openTeams" class="submenu">
            <div v-for="entry in navigation.teams" :key="entry.entity.id">
              <div class="nav-entry" :title="entry.entity.name">
                <AppIcon name="agents" size="14" />
                <span>{{ entry.entity.name }}</span>
                <span class="entry-actions">
                  <button class="icon-btn" title="Edit team" aria-label="Edit team" @click.stop="editTeam(entry.entity)"><AppIcon name="edit" size="14" /></button>
                </span>
              </div>
              <div class="submenu">
                <RouterLink v-for="leaf in entry.leaves" :key="leaf.key" class="leaf" :to="leaf.to" :title="leaf.title">
                  <AppIcon :name="leaf.icon" size="14" />
                  <span class="label">{{ leaf.label }}</span>
                  <span class="sim-dot sm" v-if="leaf.feature && isSimulated(leaf.feature)"></span>
                </RouterLink>
              </div>
            </div>
          </div>

          <!-- Sources -->
          <div class="nav-entry" :class="{ open: openSources }" title="Sources" @click="openSources = !openSources">
            <AppIcon name="folder" size="18" />
            <span>Sources</span>
            <AppIcon class="chevron" name="chevron" size="14" />
            <span class="entry-actions">
              <button class="icon-btn" title="New source" aria-label="New source" @click.stop="addSource"><AppIcon name="plus" size="14" /></button>
            </span>
          </div>
          <div v-show="openSources" class="submenu">
            <p v-if="!sources.length" class="nav-empty">Add a local project directory</p>
            <div v-for="source in sources" :key="source.id" class="nav-entry" :title="source.name">
              <AppIcon name="folder" size="14" />
              <span class="label">{{ source.name }}</span>
              <span class="source-path" :title="source.path">{{ source.path }}</span>
              <span class="entry-actions">
                <button class="icon-btn" title="Edit source" aria-label="Edit source" @click.stop="editSource(source)"><AppIcon name="edit" size="14" /></button>
                <button class="icon-btn" title="Delete source" aria-label="Delete source" @click.stop="removeEntity('source', source.id, source.name)"><AppIcon name="delete" size="14" /></button>
              </span>
            </div>
          </div>
        </div>
      </div>
    </nav>

  </aside>
  <Teleport to="body">
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
  </Teleport>
</template>
