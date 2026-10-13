<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import SimBanner from './components/SimBanner.vue'
import AppIcon from './components/AppIcon.vue'
import { boardStore } from './store/board.js'
import { theme, toggleTheme } from './theme.js'
import { hasSimulation, setAll } from './api/index.js'

const route = useRoute()
const sidebarOpen = ref(false)

onMounted(() => {
  boardStore.load()
})

function toggleSimulation() {
  setAll(!hasSimulation.value)
}
</script>

<template>
  <div class="app">
    <AppSidebar :open="sidebarOpen" />
    <div v-if="sidebarOpen" class="scrim" @click="sidebarOpen = false"></div>

    <div class="content">
      <SimBanner />
      <header class="topbar">
        <button class="icon-btn menu-toggle" title="Menu" @click="sidebarOpen = true">
          <AppIcon name="menu" />
        </button>
        <h1>{{ route.meta.title || 'Multi-Agent' }}</h1>
        <div class="spacer"></div>
        <button
          class="button ghost"
          :title="hasSimulation ? 'Disable simulated data' : 'Enable simulated data'"
          @click="toggleSimulation"
        >
          <span class="sim-dot sm"></span>
          Sim
        </button>
        <button class="icon-btn" :title="theme.mode === 'dark' ? 'Light theme' : 'Dark theme'" @click="toggleTheme">
          <AppIcon :name="theme.mode === 'dark' ? 'sun' : 'moon'" />
        </button>
      </header>

      <main class="main">
        <RouterView />
      </main>
    </div>
  </div>
</template>
