<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from './AppIcon.vue'
import { boardStore } from '../store/board.js'
import { isSimulated } from '../api/index.js'

defineProps({ open: { type: Boolean, default: false } })

const route = useRoute()
const boardsOpen = ref(true)
const agentsOpen = ref(true)
const openBoard = ref('kanban')

const boardItems = [
  { to: '/boards/columns', label: 'Columns', feature: 'columns' },
  { to: '/boards/statuses', label: 'Statuses', feature: 'statuses' },
  { to: '/boards/transitions', label: 'Transitions', feature: 'transitions' },
  { to: '/boards/issues', label: 'Issues', feature: 'issueTypes' },
]

const agentItems = [
  { to: '/agents/roles', label: 'Roles', feature: 'roles' },
  { to: '/agents/profiles', label: 'Profiles', feature: 'profiles' },
  { to: '/agents/experiences', label: 'Experiences', feature: 'experiences' },
]

function toggleBoard(id) {
  openBoard.value = openBoard.value === id ? null : id
}
</script>

<template>
  <aside class="sidebar" :class="{ open }">
    <div class="brand">
      <span class="logo"></span>
      <span>Multi-Agent</span>
    </div>

    <nav class="nav">
      <div class="nav-group">
        <div
          class="nav-entry"
          :class="{ open: boardsOpen, active: route.path.startsWith('/board') }"
          @click="boardsOpen = !boardsOpen"
        >
          <AppIcon name="board" />
          <span>Boards</span>
          <AppIcon class="chevron" name="chevron" size="16" />
          <span class="entry-actions">
            <button class="icon-btn" title="New board" @click.stop>
              <AppIcon name="plus" size="16" />
            </button>
          </span>
        </div>

        <div v-show="boardsOpen" class="submenu">
          <div v-for="board in boardStore.state.boards" :key="board.id">
            <div class="nav-entry" @click="toggleBoard(board.id)">
              <span class="sim-dot sm" v-if="isSimulated('board')"></span>
              <span>{{ board.name }}</span>
              <AppIcon class="chevron" :class="{ open: openBoard === board.id }" name="chevron" size="14" />
            </div>
            <div v-show="openBoard === board.id" class="submenu">
              <RouterLink v-for="item in boardItems" :key="item.to" class="submenu-item" :to="item.to">
                <span>{{ item.label }}</span>
                <span class="sim-dot sm" v-if="isSimulated(item.feature)"></span>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>

      <div class="nav-group">
        <div
          class="nav-entry"
          :class="{ open: agentsOpen, active: route.path.startsWith('/agents') }"
          @click="agentsOpen = !agentsOpen"
        >
          <AppIcon name="agents" />
          <span>Agents</span>
          <AppIcon class="chevron" name="chevron" size="16" />
          <span class="entry-actions">
            <button class="icon-btn" title="New agent" @click.stop>
              <AppIcon name="plus" size="16" />
            </button>
          </span>
        </div>

        <div v-show="agentsOpen" class="submenu">
          <RouterLink v-for="item in agentItems" :key="item.to" class="submenu-item" :to="item.to">
            <span>{{ item.label }}</span>
            <span class="sim-dot sm" v-if="isSimulated(item.feature)"></span>
          </RouterLink>
        </div>
      </div>
    </nav>
  </aside>
</template>
