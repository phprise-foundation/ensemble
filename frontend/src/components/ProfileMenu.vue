<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../store/session.js'

const router = useRouter()
const open = ref(false)
const root = ref(null)
const trigger = ref(null)

const fullName = computed(() => session.displayName || session.login)
const label = computed(() => (session.login ? `${fullName.value} (${session.login})` : fullName.value))

function close(refocus) {
  open.value = false
  if (refocus) {
    trigger.value?.focus()
  }
}

function toggle() {
  open.value = !open.value
}

function openProfile() {
  close(false)
  router.push('/profile')
}

function signOut() {
  close(false)
  session.signOut()
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    close(true)
  }
}

function onClickOutside(event) {
  if (open.value && root.value && !root.value.contains(event.target)) {
    close(false)
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', onClickOutside)
})
</script>

<template>
  <div ref="root" class="profile-menu">
    <button
      ref="trigger"
      class="profile-trigger"
      type="button"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-label="label"
      @click="toggle"
    >
      <span class="profile-avatar" aria-hidden="true">{{ session.initial }}</span>
      <span class="profile-name" :title="fullName">{{ session.label }}</span>
    </button>

    <div v-if="open" class="profile-dropdown" role="menu">
      <button class="profile-item" type="button" role="menuitem" @click="openProfile">profile</button>
      <button class="profile-item" type="button" role="menuitem" @click="signOut">logout</button>
    </div>
  </div>
</template>
