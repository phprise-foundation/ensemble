<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import SimBanner from './components/SimBanner.vue'
import AppIcon from './components/AppIcon.vue'
import { boardStore } from './store/board.js'
import { theme, toggleTheme } from './theme.js'
import { hasSimulation, setAll } from './api/index.js'

const route = useRoute()
const sidebarOpen = ref(false)
const menuToggle = ref(null)
const isMobile = ref(false)
let mediaQuery = null

function sidebarMediaQuery() {
  if (typeof document === 'undefined') {
    return null
  }
  const value = getComputedStyle(document.documentElement).getPropertyValue('--breakpoint-sidebar').trim()
  return window.matchMedia(`(max-width: ${value || '0px'})`)
}

function updateIsMobile(event) {
  isMobile.value = event.matches
}

onMounted(() => {
  boardStore.load()
  mediaQuery = sidebarMediaQuery()
  if (mediaQuery) {
    isMobile.value = mediaQuery.matches
    mediaQuery.addEventListener('change', updateIsMobile)
  }
})

onUnmounted(() => {
  mediaQuery?.removeEventListener('change', updateIsMobile)
})

watch(sidebarOpen, (isOpen, wasOpen) => {
  if (wasOpen && !isOpen) {
    nextTick(() => {
      menuToggle.value?.focus()
    })
  }
})

function toggleSimulation() {
  setAll(!hasSimulation.value)
}
</script>

<template>
  <div class="app">
    <AppSidebar :open="sidebarOpen" :is-mobile="isMobile" @close="sidebarOpen = false" />
    <div v-if="sidebarOpen && isMobile" class="scrim" @click="sidebarOpen = false"></div>

    <div class="content" :inert="sidebarOpen && isMobile ? true : undefined">
      <SimBanner />
      <header class="topbar">
        <button
          ref="menuToggle"
          class="icon-btn menu-toggle"
          title="Menu"
          aria-label="Menu"
          :aria-expanded="sidebarOpen"
          aria-controls="app-sidebar"
          @click="sidebarOpen = true"
        >
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
