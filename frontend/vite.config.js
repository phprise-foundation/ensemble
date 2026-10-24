import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fakeBackend } from './plugin/fake-backend.js'

// VITE_API_URL is the origin of the real PHP API (e.g. http://127.0.0.1:8081).
// When it is set, /api is proxied there and the fake backend stays out: the
// sign-in gate then verifies against the real backend. When it is unset, a
// single `npm run dev` serves the prototype on the fake backend. The browser
// bundle reads the same variable only as the real-mode flag (client.js); it
// never fetches the origin itself, everything goes same-origin.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backend = env.VITE_API_URL
  const plugins = [vue()]
  if (!backend) {
    plugins.push(fakeBackend())
  }

  return {
    plugins,
    server: backend ? { proxy: { '/api': { target: backend, changeOrigin: true } } } : {},
  }
})
