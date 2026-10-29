<script setup>
import { computed, onMounted, ref } from 'vue'
import AppIcon from '../components/AppIcon.vue'
import { boardStore } from '../store/board.js'
import { dialog } from '../store/dialog.js'
import { toast } from '../store/toast.js'

const secrets = computed(() => boardStore.state.secrets)
const loading = ref(true)
const error = ref('')
const creating = ref(false)
const rotating = ref(null)
const draft = ref({ name: '', value: '' })
const formError = ref('')
const saving = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    await boardStore.loadSecrets()
  } catch (problem) {
    error.value = problem?.message ?? 'Could not load the secrets.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  draft.value = { name: '', value: '' }
  formError.value = ''
  creating.value = true
}

function openRotate(secret) {
  draft.value = { name: secret.name, value: '' }
  formError.value = ''
  rotating.value = secret
}

function close() {
  creating.value = false
  rotating.value = null
  formError.value = ''
  draft.value = { name: '', value: '' }
}

async function save() {
  formError.value = ''
  if (!draft.value.name.trim()) {
    formError.value = 'Name is required'
    return
  }
  if (!draft.value.value) {
    formError.value = 'Value is required'
    return
  }
  saving.value = true
  try {
    await boardStore.createSecret(draft.value.name.trim(), draft.value.value)
    toast.success(`"${draft.value.name.trim()}" saved.`)
    close()
  } catch (problem) {
    formError.value = problem?.message ?? 'Could not save the secret.'
  } finally {
    saving.value = false
  }
}

async function rotate() {
  formError.value = ''
  if (!draft.value.value) {
    formError.value = 'Value is required'
    return
  }
  saving.value = true
  try {
    await boardStore.rotateSecret(rotating.value.id, draft.value.value)
    toast.success(`"${rotating.value.name}" rotated.`)
    close()
  } catch (problem) {
    formError.value = problem?.message ?? 'Could not rotate the secret.'
  } finally {
    saving.value = false
  }
}

async function remove(secret) {
  const ok = await dialog.confirm({
    title: 'Delete?',
    message: `"${secret.name}" will be removed. Repositories using it lose access.`,
    confirmLabel: 'Delete',
    danger: true,
  })
  if (!ok) return
  try {
    await boardStore.deleteSecret(secret.id)
    toast.success(`"${secret.name}" deleted.`)
  } catch (problem) {
    toast.error(problem.message)
  }
}

function format(value) {
  return value ? new Date(value).toLocaleString() : '—'
}
</script>

<template>
  <section class="crud secrets-page">
    <header class="crud-header">
      <h2>Secrets</h2>
      <span class="spacer"></span>
      <button class="button" @click="openCreate"><AppIcon name="plus" size="16" /> New secret</button>
    </header>

    <p class="muted pad">
      Credentials are encrypted and never shown again. Use them on a repository to clone and
      push private code.
    </p>
    <p v-if="error" class="error pad">{{ error }}</p>

    <div class="table">
      <div class="table-row head">
        <span>Name</span>
        <span>Updated</span>
        <span class="actions">Actions</span>
      </div>
      <div class="table-row" v-for="secret in secrets" :key="secret.id">
        <span>{{ secret.name }}</span>
        <span>{{ format(secret.updatedAt) }}</span>
        <span class="actions">
          <button class="icon-btn" title="Rotate" aria-label="Rotate" @click="openRotate(secret)">
            <AppIcon name="edit" size="14" />
          </button>
          <button class="icon-btn" title="Delete" aria-label="Delete" @click="remove(secret)">
            <AppIcon name="delete" size="14" />
          </button>
        </span>
      </div>
      <p v-if="!loading && !secrets.length" class="muted pad">No secrets yet.</p>
    </div>

    <div v-if="creating" class="modal" @click.self="close">
      <div class="modal-card">
        <header class="modal-header">
          <h3>New secret</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="close">
            <AppIcon name="close" size="20" />
          </button>
        </header>
        <div class="modal-body">
          <label class="field"><span>Name</span><input v-model="draft.name" type="text" /></label>
          <label class="field">
            <span>Value</span>
            <input v-model="draft.value" type="password" autocomplete="new-password" />
            <small class="muted">The token is encrypted; it is never displayed again.</small>
          </label>
          <p v-if="formError" class="error">{{ formError }}</p>
        </div>
        <footer class="modal-footer">
          <button class="button ghost" @click="close">Cancel</button>
          <button class="button" :disabled="saving" @click="save">
            <AppIcon name="check" size="16" /> Save
          </button>
        </footer>
      </div>
    </div>

    <div v-if="rotating" class="modal" @click.self="close">
      <div class="modal-card">
        <header class="modal-header">
          <h3>Rotate "{{ rotating.name }}"</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="close">
            <AppIcon name="close" size="20" />
          </button>
        </header>
        <div class="modal-body">
          <label class="field">
            <span>New value</span>
            <input v-model="draft.value" type="password" autocomplete="new-password" />
            <small class="muted">Replacing the value keeps the same secret reference.</small>
          </label>
          <p v-if="formError" class="error">{{ formError }}</p>
        </div>
        <footer class="modal-footer">
          <button class="button ghost" @click="close">Cancel</button>
          <button class="button" :disabled="saving" @click="rotate">
            <AppIcon name="check" size="16" /> Save
          </button>
        </footer>
      </div>
    </div>
  </section>
</template>
