<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../store/session.js'

const router = useRouter()
const open = ref(false)
const root = ref(null)
const trigger = ref(null)

function handleClickOutside(event) {
  if (open.value && root.value && !root.value.contains(event.target)) {
    open.value = false
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    open.value = false
    trigger.value?.focus()
  }
}

function goProfile() {
  open.value = false
  router.push('/profile')
}

async function toggle() {
  const entering = !open.value
  open.value = entering
  if (entering) {
    await nextTick()
    root.value?.querySelector('[role="menuitem"]')?.focus()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="root" class="profile-menu">
    <button
      ref="trigger"
      class="profile-trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-label="`Usuário ${session.label} (${session.login})`"
      :title="`${session.label} (${session.login})`"
      @click="toggle"
    >
      <span class="profile-avatar">{{ session.initial }}</span>
      <span class="profile-name" :title="session.label">{{ session.label }}</span>
    </button>
    <div v-if="open" class="profile-dropdown" role="menu">
      <button class="profile-item" role="menuitem" @click="goProfile">profile</button>
      <button class="profile-item" role="menuitem" @click="session.signOut()">logout</button>
    </div>
  </div>
</template>
