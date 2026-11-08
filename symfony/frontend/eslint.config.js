import pluginVue from 'eslint-plugin-vue'
import prettierConfig from '@vue/eslint-config-prettier'
import globals from 'globals'

export default [
  // Build outputs: minified bundles take minutes to lint and are not source.
  { ignores: ['dist/', 'storybook-static/', 'coverage/'] },
  ...pluginVue.configs['flat/recommended'],
  prettierConfig,
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'off',
    },
    languageOptions: {
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
  },
]
