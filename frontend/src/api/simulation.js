import { computed, reactive } from 'vue'

export const FEATURES = [
  { key: 'board', label: 'Board' },
  { key: 'issues', label: 'Issues' },
  { key: 'columns', label: 'Columns' },
  { key: 'statuses', label: 'Statuses' },
  { key: 'transitions', label: 'Transitions' },
  { key: 'issueTypes', label: 'Issue types' },
  { key: 'agents', label: 'Agents' },
  { key: 'roles', label: 'Roles' },
  { key: 'profiles', label: 'Profiles' },
  { key: 'experiences', label: 'Experiences' },
]

function persisted(key, fallback) {
  if (typeof localStorage === 'undefined') {
    return fallback
  }
  const value = localStorage.getItem(key)
  if (value === null) {
    return fallback
  }
  return value === '1'
}

export const simulation = reactive({
  features: Object.fromEntries(FEATURES.map((feature) => [feature.key, persisted(`sim.${feature.key}`, true)])),
})

export function isSimulated(feature) {
  return simulation.features[feature] !== false
}

export function setFeature(feature, simulated) {
  simulation.features[feature] = simulated
  localStorage.setItem(`sim.${feature}`, simulated ? '1' : '0')
}

export function setAll(simulated) {
  for (const feature of FEATURES) {
    setFeature(feature.key, simulated)
  }
}

export const simulatedFeatures = computed(() =>
  FEATURES.filter((feature) => isSimulated(feature.key)).map((feature) => feature.label),
)

export const hasSimulation = computed(() => simulatedFeatures.value.length > 0)

function applyQueryOverride() {
  if (typeof window === 'undefined') {
    return
  }
  const value = new URLSearchParams(window.location.search).get('sim')
  if (value === '1' || value === '0') {
    setAll(value === '1')
  }
}

applyQueryOverride()
