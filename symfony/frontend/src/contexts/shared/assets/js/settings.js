export default {
  FOLDER_NAME: 'default', // Do not change this value in the contexts
  TITLE: 'Vue App Title',
  DEFAULT_LOCALE: 'en',
  LOCALE_LIST_URL: '',
  META: {
    DESCRIPTION: 'Sample Vue3 app',
    TAGS: ['Vue3', 'App'],
  },
  STORAGE_PREFIX: '__default',
  AUTH_ACTIVE: true,
  AUTH: {
    PASSWORD_RECOVERY: true,
  },
  PAGES: {
    ENTRY_PAGE: 'login',
    AUTH_DEFAULT: {
      ROLE_USER: 'home',
      ROLE_MANAGER: 'admin',
      ROLE_ADMIN: 'admin',
    },
    ENABLED: {
      WELCOME: false,
    },
  },
  SHOW_HEADER: true,
  SHOW_FOOTER: true,
  // Emits the Storybook component catalog next to the app (dist/storybook) during the build.
  BUILD_COMPONENT_CATALOG: false,
}
