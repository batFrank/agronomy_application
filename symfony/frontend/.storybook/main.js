/**
 * The catalog reuses the project's vite.config.js (alias, sass, the injected __APP_SETTINGS__),
 * so it is built per client exactly like the app: set VITE_CLIENT before running it.
 *
 * @type { import('@storybook/vue3-vite').StorybookConfig }
 */
const config = {
  framework: '@storybook/vue3-vite',
  stories: ['../src/**/*.stories.js'],
  addons: ['@storybook/addon-docs', '@storybook/addon-a11y'],
  core: {
    disableTelemetry: true,
  },
}

export default config
