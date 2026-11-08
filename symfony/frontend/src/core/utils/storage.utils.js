class StorageUtils {
  static setValue(name = '', value = '', duration) {
    if (!value) this.removeValue(name)
    document.cookie = `${window.$settings.STORAGE_PREFIX}${name}=${value}; secure; max-age=${duration}; path=/;`
  }

  static getValue(name) {
    const regex = new RegExp(`(?:(?:^|.*;\\s*)${window.$settings.STORAGE_PREFIX}${name}\\s*=\\s*([^;]*).*$)|^.*$`)
    return document.cookie.replace(regex, '$1')
  }

  static removeValue(name) {
    document.cookie = `${window.$settings.STORAGE_PREFIX}${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
  }
}

export default StorageUtils
