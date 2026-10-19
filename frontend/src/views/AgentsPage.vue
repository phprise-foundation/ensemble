<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { boardStore } from '../store/board.js'
import AppIcon from './../components/AppIcon.vue'
import { isSimulated } from '../api/index.js'

const route = useRoute()
const agents = computed(() => boardStore.agentsOf(route.params.teamId))
const roles = computed(() => boardStore.rolesOf(route.params.teamId))
const profiles = computed(() => boardStore.profilesOf(route.params.teamId))

const editing = ref(false)
const draft = ref({})
const error = ref('')

function openCreate() {
  draft.value = { teamId: route.params.teamId, name: '', whoAmI: '', age: null, maritalStatus: 'Single', roleIds: [], profileId: profiles.value[0]?.id || '', portrait: '', avatarColor: '#4c9aff' }
  error.value = ''
  editing.value = true
}

function openEdit(agent) {
  draft.value = JSON.parse(JSON.stringify(agent))
  draft.value.__existing = true
  error.value = ''
  editing.value = true
}

function toggleRole(roleId) {
  const current = draft.value.roleIds || []
  draft.value.roleIds = current.includes(roleId) ? current.filter((id) => id !== roleId) : [...current, roleId]
}

function roleNames(agent) {
  return (agent.roleIds || []).map((id) => boardStore.role(id)?.name).filter(Boolean).join(', ') || 'No role'
}

function onPortrait(event) {
  const file = event.target.files?.[0]
  if (!file) {
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    draft.value.portrait = reader.result
  }
  reader.readAsDataURL(file)
}

async function save() {
  error.value = ''
  if (!draft.value.name) {
    error.value = 'Name is required'
    return
  }
  try {
    await boardStore.saveEntity('agents', draft.value)
    editing.value = false
  } catch (problem) {
    error.value = problem.message
  }
}

async function remove(agent) {
  if (!window.confirm(`Delete "${agent.name}"?`)) {
    return
  }
  await boardStore.removeEntity('agents', agent.id)
}

onMounted(() => {
  if (route.query.new === '1') {
    openCreate()
  }
})
</script>

<template>
  <div class="crud">
    <header class="crud-header">
      <h2>Agents</h2>
      <span v-if="isSimulated('agents')" class="badge sim"><span class="sim-dot sm"></span> Prototype</span>
      <div class="spacer"></div>
      <button class="button" @click="openCreate"><AppIcon name="plus" size="16" /> New agent</button>
    </header>

    <div class="grid">
      <article class="agent-card" v-for="agent in agents" :key="agent.id">
        <div class="portrait lg" :style="agent.portrait ? { backgroundImage: `url(${agent.portrait})`, backgroundSize: 'cover' } : { background: agent.avatarColor }">
          <template v-if="!agent.portrait">{{ agent.name.charAt(0) }}</template>
        </div>
        <div class="agent-info">
          <strong>{{ agent.name }}</strong>
          <div class="muted small">{{ roleNames(agent) }}</div>
          <div class="muted small">{{ boardStore.profile(agent.profileId)?.name || 'No profile' }}</div>
          <p class="muted small">{{ agent.whoAmI }}</p>
          <div class="agent-actions">
            <button class="icon-btn" title="Edit" aria-label="Edit" @click="openEdit(agent)"><AppIcon name="edit" size="14" /></button>
            <button class="icon-btn" title="Delete" aria-label="Delete" @click="remove(agent)"><AppIcon name="delete" size="14" /></button>
          </div>
        </div>
      </article>
    </div>

    <div v-if="editing" class="modal" @click.self="editing = false">
      <div class="modal-card">
        <header class="modal-header">
          <h3>{{ draft.id ? 'Edit agent' : 'New agent' }}</h3>
          <button class="icon-btn" title="Close" aria-label="Close" @click="editing = false"><AppIcon name="close" size="20" /></button>
        </header>

        <div class="modal-body">
          <div class="agent-preview">
            <div class="portrait lg" :style="draft.portrait ? { backgroundImage: `url(${draft.portrait})`, backgroundSize: 'cover' } : { background: draft.avatarColor }">
              <template v-if="!draft.portrait">{{ (draft.name || '?').charAt(0) }}</template>
            </div>
            <label class="field">
              <span>Portrait (3x4)</span>
              <input type="file" accept="image/*" @change="onPortrait" />
            </label>
          </div>

          <label class="field"><span>Name</span><input type="text" v-model="draft.name" /></label>
          <label class="field"><span>Who am I</span><textarea rows="2" v-model="draft.whoAmI"></textarea></label>
          <label class="field"><span>Age</span><input type="number" v-model.number="draft.age" /></label>
          <label class="field">
            <span>Marital status</span>
            <select v-model="draft.maritalStatus">
              <option>Single</option><option>Married</option><option>Divorced</option><option>Widowed</option><option>Other</option>
            </select>
          </label>
          <label class="field">
            <span>Roles</span>
            <div class="checks">
              <label v-for="role in roles" :key="role.id" class="inline">
                <input type="checkbox" :checked="(draft.roleIds || []).includes(role.id)" @change="toggleRole(role.id)" />
                {{ role.name }}
              </label>
            </div>
          </label>
          <label class="field">
            <span>Profile</span>
            <select v-model="draft.profileId">
              <option v-for="profile in profiles" :key="profile.id" :value="profile.id">{{ profile.name }}</option>
            </select>
          </label>

          <p v-if="error" class="error">{{ error }}</p>
        </div>

        <footer class="modal-footer">
          <button class="button ghost" @click="editing = false">Cancel</button>
          <button class="button" @click="save"><AppIcon name="check" size="16" /> Save</button>
        </footer>
      </div>
    </div>
  </div>
</template>
