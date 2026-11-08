import StorageUtils from '@/core/utils/storage.utils.js'
import { ONE_DAY_IN_MS } from '@/core/constants/general.constant.js'

const STORAGE_KEYS = {
  TOKEN: '_tk',
  REFRESH_TOKEN: '_rtk',
  LOCALE: '_locale',
}

export class StorageService {
  #setValue(name = '', value = '', expires) {
    if (!value) return this.#removeValue(name)
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires}; path=/;`
  }

  #getValue(name = '') {
    const match = document.cookie.match(
      new RegExp('(?:^|; )' + name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&') + '=([^;]*)')
    )
    return match ? decodeURIComponent(match[1]) : ''
  }

  #removeValue(name = '') {
    document.cookie = `${encodeURIComponent(name)}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;`
  }

  static setToken(value) {
    new StorageService().#setValue(STORAGE_KEYS.TOKEN, value, ONE_DAY_IN_MS)
  }

  static getToken() {
    return new StorageService().#getValue(STORAGE_KEYS.TOKEN)
  }

  static setRefreshToken(value) {
    new StorageService().#setValue(STORAGE_KEYS.REFRESH_TOKEN, value, ONE_DAY_IN_MS)
  }

  static getRefreshToken() {
    return new StorageService().#getValue(STORAGE_KEYS.REFRESH_TOKEN)
  }

  static setLocale(value = '') {
    StorageUtils.setValue(STORAGE_KEYS.LOCALE, value, ONE_DAY_IN_MS)
  }
  static getLocale() {
    return StorageUtils.getValue(STORAGE_KEYS.LOCALE)
  }
}

export default StorageService
