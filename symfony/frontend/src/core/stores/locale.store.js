import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import ApiService from '@/core/services/api.service.js'
import StorageService from '@/core/services/storage.service.js'
import { getBrowserLocales } from '@/core/utils/misc.utils.js'

export const useLocalStore = defineStore('localeStore', () => {
  const locales = ref({ en: 'English' })
  async function fetchLocales() {
    if (!window.$settings.LOCALE_LIST_URL) return null
    const { data, error } = await ApiService.get(window.$settings.LOCALE_LIST_URL)
    if (error) return null
    locales.value = data
  }

  const locale = ref('')
  function loadLocale() {
    if (currentLocale.value) {
      locale.value = currentLocale.value
      return null
    }

    const browserLocales = getBrowserLocales()
    if (browserLocales.length) {
      const found = browserLocales.find((locale) => locale in locales.value)
      if (found) {
        locale.value = found
        return null
      }
    }

    locale.value = clientDefaultLocale.value
  }

  const currentLocale = computed(() => locale.value || StorageService.getLocale() || window.$settings.DEFAULT_LOCALE)
  const clientDefaultLocale = computed(() => window.$settings.DEFAULT_LOCALE)

  return {
    currentLocale,
    clientDefaultLocale,
    locales,
    fetchLocales,
    loadLocale,
  }
})
