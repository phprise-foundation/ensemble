import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fakeBackend } from './plugin/fake-backend.js'

// The fake backend serves /api and persists to .data/db.json, so a single
// `npm run dev` is enough to exercise the whole UX. Point VITE_API_URL at the
// real PHP API when it exists to bypass the fake backend.
export default defineConfig({
  plugins: [vue(), fakeBackend()],
})
