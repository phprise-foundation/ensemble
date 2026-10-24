<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import ProfileMenu from './components/ProfileMenu.vue'
import SimBanner from './components/SimBanner.vue'
import AppIcon from './components/AppIcon.vue'
import AppCopilot from './components/AppCopilot.vue'
import AppConsole from './components/AppConsole.vue'
import DialogHost from './components/DialogHost.vue'
import ToastHost from './components/ToastHost.vue'
import { boardStore } from './store/board.js'
import { CONSOLE_HEIGHT_STEP, DEFAULT_CONSOLE_HEIGHT, clampHeight } from './store/consoleHeight.js'
import { session } from './store/session.js'
import { theme, toggleTheme } from './theme.js'
import { hasSimulation, setAll } from './api/index.js'

const route = useRoute()
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const menuToggle = ref(null)
const isMobile = ref(false)
const isNarrow = ref(false)
const copilotOpen = ref(true)
const consoleOpen = ref(false)
const consoleHeight = ref(DEFAULT_CONSOLE_HEIGHT)
const consoleHeightStyle = computed(() => ({ '--console-height': `${consoleHeight.value}px` }))
let sidebarQuery = null
let narrowQuery = null

const signInLogin = ref('')
const signInPassword = ref('')
const signInError = ref('')

async function submitSignIn() {
  signInError.value = ''
  try {
    await session.signIn(signInLogin.value.trim(), signInPassword.value)
    signInPassword.value = ''
    await boardStore.load()
  } catch (error) {
    signInError.value = error.message || 'Sign in failed'
  }
}

function adjustConsoleHeight(delta) {
  consoleHeight.value = clampHeight(consoleHeight.value + delta)
}

function setConsoleHeight(value) {
  consoleHeight.value = clampHeight(value)
}

function resetConsoleHeight() {
  consoleHeight.value = DEFAULT_CONSOLE_HEIGHT
}

function handleKeyboardShortcut(event) {
  if (isMobile.value) {
    return
  }
  if (event.key === 'b' && (event.ctrlKey || event.metaKey) && event.altKey) {
    event.preventDefault()
    copilotOpen.value = !copilotOpen.value
    return
  }
  if (event.key === 'j' && (event.ctrlKey || event.metaKey)) {
    event.preventDefault()
    consoleOpen.value = !consoleOpen.value
    return
  }
  if (event.key === 'b' && (event.ctrlKey || event.metaKey)) {
    event.preventDefault()
    sidebarCollapsed.value = !sidebarCollapsed.value
  }
}

function mediaQueryFor(property, offset = 0) {
  if (typeof document === 'undefined') {
    return null
  }
  const value = getComputedStyle(document.documentElement).getPropertyValue(property).trim()
  const declared = Number.parseFloat(value)
  const limit = Number.isFinite(declared) ? declared + offset : 0
  return window.matchMedia(`(max-width: ${limit}px)`)
}

function updateViewportFlags() {
  if (sidebarQuery) {
    isMobile.value = sidebarQuery.matches
  }
  if (narrowQuery) {
    isNarrow.value = narrowQuery.matches
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyboardShortcut)
  sidebarQuery = mediaQueryFor('--breakpoint-sidebar')
  narrowQuery = mediaQueryFor('--breakpoint-mobile', -1)
  if (sidebarQuery) {
    sidebarQuery.addEventListener('change', updateViewportFlags)
  }
  if (narrowQuery) {
    narrowQuery.addEventListener('change', updateViewportFlags)
  }
  updateViewportFlags()
  window.addEventListener('resize', updateViewportFlags)
  session.load().then(() => {
    boardStore.load()
  })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyboardShortcut)
  window.removeEventListener('resize', updateViewportFlags)
  sidebarQuery?.removeEventListener('change', updateViewportFlags)
  narrowQuery?.removeEventListener('change', updateViewportFlags)
  document.body.classList.remove('drawer-locked')
})

watch([sidebarOpen, isNarrow], ([isOpen, narrow]) => {
  document.body.classList.toggle('drawer-locked', isOpen && narrow)
})

watch(sidebarOpen, (isOpen, wasOpen) => {
  if (wasOpen && !isOpen) {
    nextTick(() => {
      menuToggle.value?.focus()
    })
  }
})

const simActive = computed(() => hasSimulation.value)

function toggleSimulation() {
  setAll(!hasSimulation.value)
}
</script>

<template>
  <div class="app" v-if="session.signedIn">
    <AppSidebar :open="sidebarOpen" :is-mobile="isMobile" :narrow="isNarrow" :collapsed="sidebarCollapsed" @close="sidebarOpen = false" />
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
        <div class="topbar-actions">
          <button
            data-topbar-slot="sim"
            class="button ghost sim-toggle"
            title="Alternar dados simulados"
            aria-label="Alternar dados simulados"
            :aria-pressed="simActive"
            @click="toggleSimulation"
          >
            <span class="sim-dot sm"></span>
            Sim
          </button>
          <button
            v-if="!isMobile"
            data-topbar-slot="grid-left"
            class="icon-btn sidebar-toggle"
            title="Toggle sidebar"
            aria-label="Toggle sidebar"
            aria-controls="app-sidebar"
            :aria-expanded="!sidebarCollapsed"
            @click="sidebarCollapsed = !sidebarCollapsed"
          >
            <AppIcon name="sidebar" />
          </button>
          <button
            v-if="!isMobile"
            data-topbar-slot="grid-bottom"
            class="icon-btn console-toggle"
            :title="consoleOpen ? 'Ocultar console' : 'Mostrar console'"
            aria-label="Alternar console"
            :aria-expanded="consoleOpen"
            aria-controls="app-console"
            @click="consoleOpen = !consoleOpen"
          >
            <AppIcon name="console" />
          </button>
          <button
            v-if="!isMobile"
            data-topbar-slot="grid-right"
            class="icon-btn copilot-toggle"
            :title="copilotOpen ? 'Hide copilot' : 'Show copilot'"
            aria-label="Toggle copilot"
            :aria-expanded="copilotOpen"
            aria-controls="app-copilot"
            @click="copilotOpen = !copilotOpen"
          >
            <AppIcon name="copilot" />
          </button>
          <button
            data-topbar-slot="theme"
            class="icon-btn theme-toggle"
            :title="theme.mode === 'dark' ? 'Light theme' : 'Dark theme'"
            :aria-label="theme.mode === 'dark' ? 'Light theme' : 'Dark theme'"
            @click="toggleTheme"
          >
            <AppIcon :name="theme.mode === 'dark' ? 'sun' : 'moon'" />
          </button>
          <ProfileMenu data-topbar-slot="profile" />
        </div>
      </header>

      <main class="main">
        <RouterView />
      </main>
      <AppConsole
        v-if="!isMobile && consoleOpen"
        :style="consoleHeightStyle"
        :height="consoleHeight"
        :step="CONSOLE_HEIGHT_STEP"
        @resize="setConsoleHeight"
        @adjust="adjustConsoleHeight"
        @reset="resetConsoleHeight"
        @close="consoleOpen = false"
      />
    </div>

    <AppCopilot v-if="!isMobile && copilotOpen" />

    <DialogHost />
    <ToastHost />
  </div>
  <div class="session-ended" v-else>
    <form class="session-ended-box" @submit.prevent="submitSignIn">
      <h1 class="session-ended-title">Multi-Agent</h1>
      <p class="session-ended-message">Sign in to your workspace</p>
      <input v-model="signInLogin" class="input session-ended-field" aria-label="Login" autocomplete="username" />
      <input v-model="signInPassword" class="input session-ended-field" type="password" aria-label="Password" autocomplete="current-password" />
      <p class="session-ended-error" v-if="signInError">{{ signInError }}</p>
      <button class="button" type="submit">Sign in</button>
    </form>
  </div>
</template>
