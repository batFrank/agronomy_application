import { APP_COMPONENT, IMAGES, VIEWS } from '@/core/configs/loader.config.js'

export class LoaderService {
  constructor({} = {}) {}

  static findModule(modules, path) {
    const key = Object.keys(modules).findLast((key) => {
      return new RegExp(`\/${path}($|.*)`).test(key)
    })
    return key ? modules[key] : null
  }

  static importImage(imagePath) {
    if (window.$settings.FOLDER_NAME === 'default') return this.findModule(IMAGES.default, imagePath) || ''
    return LoaderService.findModule(IMAGES.clients, imagePath) || this.findModule(IMAGES.default, imagePath) || ''
  }
  static async importView(viewName = '') {
    if (window.$settings.FOLDER_NAME === 'default') {
      const viewKey = Object.keys(VIEWS.default).find((key) => key.includes(viewName))
      return VIEWS.default[viewKey].default
    }

    const normalizedViews = {
      ...Object.keys(VIEWS.default).reduce(
        (acc, cur) => ({ ...acc, [cur.replace('/src/contexts/', '')]: VIEWS.default[cur] }),
        {}
      ),
      ...Object.keys(VIEWS.clients)
        .filter((key) => key.includes(`/src/clients/${window.$settings.FOLDER_NAME}/`))
        .reduce(
          (acc, cur) => ({
            ...acc,
            [cur.replace(`/src/clients/${window.$settings.FOLDER_NAME}/`, '')]: VIEWS.clients[cur],
          }),
          {}
        ),
    }

    const viewKey = Object.keys(normalizedViews).find((key) => key.includes(viewName))
    const view = await normalizedViews[viewKey]
    return view.default
  }
  static async importComponent(name) {
    return Object.values(APP_COMPONENT[name])[0]
  }
}
