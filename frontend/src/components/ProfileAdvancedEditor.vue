<script setup>
import { computed, reactive, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const local = reactive(JSON.parse(JSON.stringify(props.modelValue || {})))
for (const key of ['identity', 'psychology', 'physical', 'communication', 'personality', 'interests', 'background']) {
  if (!local[key] || typeof local[key] !== 'object' || Array.isArray(local[key])) local[key] = {}
}

const SKIN_TONES = ['fair', 'light', 'medium', 'tan', 'olive', 'brown', 'dark']
const BODY_TYPES = ['slim', 'athletic', 'muscular', 'curvy', 'average', 'petite', 'tall']
const HAIR = ['black', 'brown', 'blonde', 'red', 'auburn', 'silver', 'white', 'blue', 'pink', 'green', 'purple', 'other']
const EYES = ['brown', 'blue', 'green', 'gray', 'hazel', 'red', 'gold', 'violet', 'heterochromia', 'other']
const VOICES = ['soft', 'calm', 'cheerful', 'energetic', 'playful', 'authoritative', 'monotone', 'seductive', 'serious', 'synthetic', 'other']
const PSYCHOLOGY = ['enthusiasm', 'formality', 'confidence', 'methodicalness', 'criticality', 'empathy', 'adaptability']

function lines(section, key) {
  return (local[section][key] || []).join('\n')
}

function setLines(section, key, value) {
  local[section][key] = value.split('\n').map((item) => item.trim()).filter(Boolean)
}

const tattoosText = computed({
  get: () => (local.physical.tattoos || []).map((item) => `${item.description} | ${item.placement}`).join('\n'),
  set: (value) => {
    local.physical.tattoos = value.split('\n').map((line) => line.trim()).filter(Boolean).map((line) => {
      const [description, placement] = line.split('|').map((part) => part.trim())
      return { description: description || line, placement: placement || 'não especificado' }
    })
  },
})

const piercingsText = computed({
  get: () => (local.physical.piercings || []).join('\n'),
  set: (value) => {
    local.physical.piercings = value.split('\n').map((item) => item.trim()).filter(Boolean)
  },
})

watch(local, () => emit('update:modelValue', JSON.parse(JSON.stringify(local))), { deep: true })
</script>

<template>
  <div class="profile-advanced">
    <details open>
      <summary>Physical</summary>
      <div class="advanced-grid">
        <label class="field"><span>Height (cm)</span><input v-model.number="local.physical.height_cm" type="number" min="0" /></label>
        <label class="field"><span>Weight (kg)</span><input v-model.number="local.physical.weight_kg" type="number" min="0" /></label>
        <label class="field"><span>Body type</span>
          <select v-model="local.physical.body_type"><option value="">—</option><option v-for="option in BODY_TYPES" :key="option" :value="option">{{ option }}</option></select>
        </label>
        <label class="field"><span>Skin tone</span>
          <select v-model="local.physical.skin_tone"><option value="">—</option><option v-for="option in SKIN_TONES" :key="option" :value="option">{{ option }}</option></select>
        </label>
        <label class="field"><span>Hair</span>
          <select v-model="local.physical.hair"><option value="">—</option><option v-for="option in HAIR" :key="option" :value="option">{{ option }}</option></select>
        </label>
        <label class="field"><span>Hair details</span><input v-model="local.physical.hair_details" type="text" /></label>
        <label class="field"><span>Eyes</span>
          <select v-model="local.physical.eyes"><option value="">—</option><option v-for="option in EYES" :key="option" :value="option">{{ option }}</option></select>
        </label>
        <label class="field"><span>Eye details</span><input v-model="local.physical.eye_details" type="text" /></label>
        <label class="field"><span>Voice</span>
          <select v-model="local.physical.voice"><option value="">—</option><option v-for="option in VOICES" :key="option" :value="option">{{ option }}</option></select>
        </label>
        <label class="field"><span>Style</span><input v-model="local.physical.style" type="text" /></label>
      </div>
      <label class="field"><span>Tattoos (one per line: description | placement)</span><textarea v-model="tattoosText" rows="3"></textarea></label>
      <label class="field"><span>Piercings (one per line)</span><textarea v-model="piercingsText" rows="3"></textarea></label>
      <label class="field"><span>Distinguishing marks</span><input v-model="local.physical.distinguishing_marks" type="text" /></label>
    </details>

    <details>
      <summary>Psychology (1–10)</summary>
      <div class="advanced-grid">
        <label v-for="attribute in PSYCHOLOGY" :key="attribute" class="field">
          <span>{{ attribute }}</span>
          <input v-model.number="local.psychology[attribute]" type="number" min="1" max="10" />
        </label>
      </div>
    </details>

    <details>
      <summary>Communication</summary>
      <label class="field"><span>Tone of voice</span><textarea v-model="local.communication.tone_of_voice" rows="3"></textarea></label>
      <label class="field"><span>Keywords (one per line)</span><textarea :value="lines('communication', 'keywords')" rows="3" @input="setLines('communication', 'keywords', $event.target.value)"></textarea></label>
      <label class="field"><span>Forbidden expressions (one per line)</span><textarea :value="lines('communication', 'forbidden_expressions')" rows="3" @input="setLines('communication', 'forbidden_expressions', $event.target.value)"></textarea></label>
      <label class="field"><span>Humor</span><input v-model="local.communication.humor" type="text" /></label>
    </details>

    <details>
      <summary>Personality</summary>
      <label class="field"><span>Traits (one per line)</span><textarea :value="lines('personality', 'traits')" rows="3" @input="setLines('personality', 'traits', $event.target.value)"></textarea></label>
      <label class="field"><span>Values (one per line)</span><textarea :value="lines('personality', 'values')" rows="2" @input="setLines('personality', 'values', $event.target.value)"></textarea></label>
      <label class="field"><span>Fears (one per line)</span><textarea :value="lines('personality', 'fears')" rows="2" @input="setLines('personality', 'fears', $event.target.value)"></textarea></label>
      <label class="field"><span>Desires (one per line)</span><textarea :value="lines('personality', 'desires')" rows="2" @input="setLines('personality', 'desires', $event.target.value)"></textarea></label>
      <label class="field"><span>Quirks (one per line)</span><textarea :value="lines('personality', 'quirks')" rows="3" @input="setLines('personality', 'quirks', $event.target.value)"></textarea></label>
      <label class="field"><span>Relationship style</span><input v-model="local.personality.relationship_style" type="text" /></label>
    </details>

    <details>
      <summary>Interests</summary>
      <label class="field"><span>Hobbies (one per line)</span><textarea :value="lines('interests', 'hobbies')" rows="3" @input="setLines('interests', 'hobbies', $event.target.value)"></textarea></label>
      <label class="field"><span>Music (one per line)</span><textarea :value="lines('interests', 'music')" rows="2" @input="setLines('interests', 'music', $event.target.value)"></textarea></label>
      <label class="field"><span>Books (one per line)</span><textarea :value="lines('interests', 'books')" rows="2" @input="setLines('interests', 'books', $event.target.value)"></textarea></label>
      <label class="field"><span>Movies (one per line)</span><textarea :value="lines('interests', 'movies')" rows="2" @input="setLines('interests', 'movies', $event.target.value)"></textarea></label>
      <label class="field"><span>Anime (one per line)</span><textarea :value="lines('interests', 'anime')" rows="2" @input="setLines('interests', 'anime', $event.target.value)"></textarea></label>
      <label class="field"><span>Games (one per line)</span><textarea :value="lines('interests', 'games')" rows="2" @input="setLines('interests', 'games', $event.target.value)"></textarea></label>
      <label class="field"><span>Food and drink (one per line)</span><textarea :value="lines('interests', 'food_and_drink')" rows="2" @input="setLines('interests', 'food_and_drink', $event.target.value)"></textarea></label>
    </details>

    <details>
      <summary>Identity and background</summary>
      <label class="field"><span>Character</span><input v-model="local.identity.character" type="text" /></label>
      <label class="field"><span>Archetype</span><input v-model="local.identity.archetype" type="text" /></label>
      <label class="field"><span>Pronouns</span><input v-model="local.identity.pronouns" type="text" /></label>
      <label class="field"><span>Notes</span><textarea v-model="local.background.notes" rows="3"></textarea></label>
    </details>
  </div>
</template>
