<script setup>
import { ref } from 'vue'
import { session } from '../store/session.js'

const name = ref(session.displayName)
const error = ref('')
const status = ref('')

async function save() {
  error.value = ''
  status.value = ''
  try {
    await session.rename(name.value)
    name.value = session.displayName
    status.value = 'Display name saved.'
  } catch (problem) {
    error.value = problem.message
  }
}
</script>

<template>
  <section class="profile-page">
    <header class="profile-head">
      <span class="profile-avatar lg" aria-hidden="true">{{ session.initial }}</span>
      <div>
        <h2>Profile</h2>
        <p class="muted">Signed in as {{ session.login }}</p>
      </div>
    </header>

    <form class="profile-form" @submit.prevent="save">
      <label class="field">
        <span>Login</span>
        <input :value="session.login" readonly />
      </label>

      <label class="field">
        <span>Display name</span>
        <input v-model="name" maxlength="40" placeholder="Your display name" />
      </label>

      <p class="muted">At most 40 characters. Leave it empty to show your login instead.</p>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="status" class="ok">{{ status }}</p>

      <button class="button" type="submit">Save</button>
    </form>
  </section>
</template>
