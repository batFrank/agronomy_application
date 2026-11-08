import '@/core/settings-init.js'
import { createApp, reactive, ref } from 'vue'
import { initSiteData } from '@/core/configs/site.config.js'
import RouterPlugin from '@/core/plugins/router.plugin.js'
import StylesPlugin from '@/core/plugins/styles.plugin.js'
import StorePlugin from '@/core/plugins/store.plugin.js'
import I18nPlugin from '@/core/plugins/i18n.plugin.js'
import SentryPlugin from '@/core/plugins/sentry.plugin.js'
import { installUiPlugins } from '@/core/plugins/ui.plugins.js'
import App from '@/contexts/shared/views/App.vue'
import { buildMenu } from '@/core/services/moduleLoader.service.js'
import * as menuState from '@/core/state/menu.state.js'
import CurrentUserModel from '@/core/models/currentUser.model.js'

async function initMockServer() {
  if (window.$settings?.START_MOCK_SERVER === 'true') {
    await import('@/core/plugins/mocks.plugin.js').then((MocksPlugin) => MocksPlugin.default.install())
  }
}
initSiteData()
initMockServer().then(async () => {
  const app = createApp(App)
  const router = RouterPlugin.install(app)
  SentryPlugin.install(app, router)
  StorePlugin.install(app, router)
  installUiPlugins(app)

  await I18nPlugin.install(app)
  await StylesPlugin.install()
  /** @type CurrentUserModel */
  const currentUser = reactive(new CurrentUserModel())
  const menuItems = ref([])
  const footerItems = ref([])
  currentUser.setOnInit(async () => {
    const { menu, footer } = await buildMenu(app, router, currentUser, menuState.resolveMenu)
    footerItems.value = footer
    menuItems.value = menu
  })
  const { menu, footer } = await buildMenu(app, router, currentUser, menuState.resolveMenu)
  footerItems.value = footer
  menuItems.value = menu
  app.provide('currentUser', currentUser)
  app.provide('menuItems', menuItems)
  app.provide('footerItems', footerItems)

  app.mount('#app')
})
