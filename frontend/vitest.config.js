import vue from '@vitejs/plugin-vue'
import { configDefaults, defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    exclude: [...configDefaults.exclude, '**/*.test.mjs'],
    // Unit tests exercise the fake-backend prototype paths: a developer
    // .env.local pointing at the real PHP API must not flip them.
    env: { VITE_API_URL: '' },
  },
})
