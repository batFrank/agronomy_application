import '@/core/settings-init.js'
import { setup } from '@storybook/vue3-vite'
import { createPinia, setActivePinia } from 'pinia'
import { createAppI18n } from '@/core/plugins/i18n.plugin.js'
import StylesPlugin from '@/core/plugins/styles.plugin.js'
import { installUiPlugins } from '@/core/plugins/ui.plugins.js'

// Storybook mounts a fresh Vue app per story and its setup hook is synchronous, so the
// asynchronous, app-independent work (locale messages, client stylesheet) is resolved once here.
const pinia = createPinia()
setActivePinia(pinia)
const i18n = await createAppI18n()
await StylesPlugin.install()

setup((app) => {
  app.use(pinia)
  app.use(i18n)
  installUiPlugins(app)
})

/** @type { import('@storybook/vue3-vite').Preview } */
const preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    a11y: {
      test: 'error',
    },
  },
  tags: ['autodocs'],
}

export default preview
