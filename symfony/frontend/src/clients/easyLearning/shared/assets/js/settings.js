export default {
  FOLDER_NAME: 'easyLearning',
  TITLE: 'easyLearning',
  PAGES: {
    AUTH_DEFAULT: {
      ROLE_USER: 'home',
      ROLE_MANAGER: 'admin',
      ROLE_ADMIN: 'admin',
    },
    ENABLED: {
      WELCOME: true,
    },
  },
  BUILD_COMPONENT_CATALOG: true,
  SENTRY: {
    DSN: 'https://9d043587574477214d83eb1b48bec787@o4511302730579968.ingest.de.sentry.io/4511303384498256',
    TRACES_SAMPLE_RATE: 1.0,
  },
}
