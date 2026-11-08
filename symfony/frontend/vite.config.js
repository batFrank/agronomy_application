import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'
import { fileURLToPath, URL } from 'node:url'
import { loadClientConfig } from './src/core/scripts/clients.scripts.js'

export default defineConfig(async ({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const clientConfig = await loadClientConfig(env.VITE_CLIENT)
  const BASE_DIR = (env?.VITE_BASE_URL || 'app').replace(/(^\/|\/$)/gi, '')

  return {
    base: `/${BASE_DIR}/`,
    define: {
      __APP_SETTINGS__: { ...clientConfig, BASE_DIR: `${BASE_DIR}`, START_MOCK_SERVER: env.START_MOCK_SERVER },
      __VUE_PROD_DEVTOOLS__: false,
    },
    plugins: [vue()],
    server: {
      host: true,
      proxy: {
        '/api': {
          target: env.VITE_API_PROXY_TARGET ?? 'http://symfony-base-apache',
          changeOrigin: true,
        },
      },
      port: 7011,
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    build: {
      rollupOptions: {
        onwarn(warning, warn) {
          if (warning.code === 'INVALID_ANNOTATION') return
          warn(warning)
        },
      },
    },
    test: {
      environment: 'happy-dom',
      include: ['src/**/*.{test,spec}.js'],
      coverage: {
        provider: 'v8',
        include: ['src/**/*.js'],
      },
    },
    publicDir: 'public',
  }
})
