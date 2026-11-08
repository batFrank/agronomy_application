import * as Sentry from '@sentry/vue'

const SentryPlugin = {
  install(app, router) {
    const dsn = window.$settings?.SENTRY?.DSN
    if (!dsn) return

    Sentry.init({
      app,
      dsn,
      integrations: [Sentry.browserTracingIntegration({ router })],
      tracesSampleRate: window.$settings?.SENTRY?.TRACES_SAMPLE_RATE ?? 1.0,
      sendDefaultPii: true,
      initialScope: {
        tags: {
          client_title: window.$settings?.TITLE,
        },
      },
    })
  },
}

export default SentryPlugin
