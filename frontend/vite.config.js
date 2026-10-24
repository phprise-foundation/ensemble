import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// VITE_API_URL is the origin of the real PHP API (e.g. http://127.0.0.1:8081).
// It is required: /api is proxied there in dev, and the sign-in gate verifies
// credentials against the backend. The production build lands in public/app/
// and the PHP front controller serves it same-origin — no proxy needed there.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backend = env.VITE_API_URL
  if (!backend && mode !== 'production' && mode !== 'test') {
    throw new Error('VITE_API_URL is required: point it at the PHP API origin (see .env.example)')
  }

  return {
    plugins: [vue()],
    base: '/app/',
    build: { outDir: '../public/app', emptyOutDir: true },
    server: backend ? { proxy: { '/api': { target: backend, changeOrigin: true } } } : {},
  }
})
