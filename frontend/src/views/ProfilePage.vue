<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../store/session.js'
import { imageFileError } from '../lib/image-upload.js'
import { normalizeImageFile } from '../lib/image-normalize.js'
import Avatar from '../components/Avatar.vue'

const router = useRouter()
const displayName = ref(session.displayName)
const draftAvatar = ref(session.avatar)
const saving = ref(false)
const saved = ref(false)
const error = ref('')

watch(
  () => [session.displayName, session.avatar],
  ([name, avatar]) => {
    displayName.value = name
    draftAvatar.value = avatar
  },
  { immediate: true },
)

const dirty = computed(
  () => displayName.value.trim() !== session.displayName || draftAvatar.value !== session.avatar,
)

const preview = computed(() =>
  draftAvatar.value === null ? '' : (draftAvatar.value || session.avatar),
)

async function pickPhoto(event) {
  error.value = ''
  const file = event.target.files?.[0]
  event.target.value = ''
  const problem = imageFileError(file)
  if (problem) {
    error.value = problem
    return
  }
  try {
    draftAvatar.value = await normalizeImageFile(file)
  } catch (failure) {
    error.value = failure?.message ?? 'Could not process the image.'
  }
}

function removePhoto() {
  error.value = ''
  draftAvatar.value = null
}

async function save() {
  if (!dirty.value || saving.value) return
  error.value = ''
  saved.value = false
  saving.value = true
  try {
    const changes = { displayName: displayName.value.trim() }
    if (draftAvatar.value !== session.avatar) {
      changes.avatar = draftAvatar.value === null ? null : draftAvatar.value
    }
    await session.updateProfile(changes)
    draftAvatar.value = session.avatar
    saved.value = true
  } catch (failure) {
    error.value = failure?.message ?? 'Could not save the profile.'
  } finally {
    saving.value = false
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
        <p class="profile-preview" aria-live="polite">
          <Avatar
            :name="session.label"
            :image="preview"
            :color="session.avatarColor"
            size="lg"
            data-test="avatar-preview"
          />
        </p>
        <label for="profile-photo">Upload photo</label>
        <input
          id="profile-photo"
          type="file"
          accept="image/png,image/jpeg,image/webp"
          data-test="photo-input"
          @change="pickPhoto"
        />
        <button type="button" data-test="remove-photo" @click="removePhoto">Remove photo</button>
        <p class="hint" :class="{ error: Boolean(error) }" data-test="file-error">
          {{ error || 'PNG, JPEG or WebP up to 2 MB.' }}
        </p>
      </div>
      <div class="field-row">
        <label for="profile-name">Name</label>
        <input
          id="profile-name"
          v-model="displayName"
          type="text"
          maxlength="40"
          autocomplete="name"
          data-test="display-name"
        />
      </div>
      <div class="field-row">
        <label for="profile-login">Login</label>
        <input id="profile-login" type="text" :value="session.login" readonly />
      </div>
      <div class="profile-actions">
        <button type="button" @click="back">back</button>
        <button type="submit" :disabled="!dirty || saving" data-test="save">
          <AppIcon name="check" size="16" /> save
        </button>
        <span v-if="saving" class="hint" role="status">Saving…</span>
        <span v-else-if="saved" class="hint" role="status" data-test="save-status">Saved.</span>
      </div>
    </form>
  </section>
</template>
