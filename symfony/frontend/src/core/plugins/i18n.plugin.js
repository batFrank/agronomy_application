import { createI18n } from 'vue-i18n'
import { useLocalStore } from '@/core/stores/locale.store.js'
import { deepObjectReplace } from '@/core/utils/misc.utils.js'
import { LOCALES } from '@/core/configs/i18n.config.js'
import StorageService from '@/core/services/storage.service.js'

function loadLocaleMessages() {
  const clientList = {
    ...Object.keys(LOCALES.clients)
      .filter((key) => key.includes(`/${window.$settings.FOLDER_NAME}/`))
      .reduce((acc, cur) => ({ ...acc, [cur]: LOCALES.clients[cur] }), {}),
  }

  const messages = {}
  Object.entries(deepObjectReplace(LOCALES.default, clientList)).forEach(([key, values]) => {
    const locale = key.match(/(\w+)\.json$/)[1]
    if (locale) messages[locale] = values
  })
  return messages
}

export async function createAppI18n() {
  const localStore = useLocalStore()
  const { fetchLocales, loadLocale } = localStore
  await fetchLocales()
  loadLocale()

  StorageService.setLocale(localStore.currentLocale)

  return createI18n({
    locale: localStore.currentLocale,
    fallbackLocale: localStore.clientDefaultLocale,
    messages: loadLocaleMessages(),
    legacy: false,
  })
}

const I18nPlugin = {
  async install(app) {
    const i18n = await createAppI18n()

    app.use(i18n)

    window.$i18n = i18n
  },
}

export default I18nPlugin
