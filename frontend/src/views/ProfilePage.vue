<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../store/session.js'
import AppIcon from './../components/AppIcon.vue'

const router = useRouter()
const name = ref(session.displayName)
const saved = ref(false)
const error = ref('')

watch(
  () => session.displayName,
  (value) => {
    name.value = value
  },
  { immediate: true },
)

async function save() {
  error.value = ''
  try {
    await session.rename(name.value.trim())
    saved.value = true
  } catch (failure) {
    error.value = failure?.message ?? 'não foi possível salvar o nome'
  }
}

function back() {
  router.back()
}
</script>

<template>
  <section class="profile-page">
    <h1>Profile</h1>
    <form class="profile-form" @submit.prevent="save">
      <div class="field-row">
        <label for="profile-name">Name</label>
        <input
          id="profile-name"
          v-model="name"
          type="text"
          maxlength="40"
          autocomplete="name"
        />
        <p class="hint" :class="{ error: Boolean(error) }">
          {{ error || 'até 40 caracteres; o login não muda.' }}
        </p>
      </div>
      <div class="field-row">
        <label for="profile-login">Login</label>
        <input id="profile-login" type="text" :value="session.login" readonly />
      </div>
      <div class="profile-actions">
        <button type="button" @click="back">back</button>
        <button type="submit"><AppIcon name="check" size="16" /> save</button>
        <span v-if="saved" class="hint" role="status">nome salvo</span>
      </div>
    </form>
  </section>
</template>
