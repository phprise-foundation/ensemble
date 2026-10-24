import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fakeBackend } from './plugin/fake-backend.js'

// The fake backend serves /api and persists to .data/db.json, so a single
// `npm run dev` is enough to exercise the whole UX. Point VITE_API_URL at the
// real PHP API (or VITE_API_PROXY at its origin) to bypass the fake backend:
// then /api is proxied to the real server and the app talks to it directly,
// signing in through POST /api/auth/login.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const realApi = env.VITE_API_PROXY || env.VITE_API_URL
  const plugins = [vue()]
  if (!realApi) {
    plugins.push(fakeBackend())
  }

  return {
    plugins,
    server: realApi
      ? { proxy: { '/api': { target: realApi, changeOrigin: true } } }
      : {},
  }
})
