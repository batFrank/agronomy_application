import { LoaderService } from '@/core/services/loader.service.js'

export function routeFactory({ path = '', name = '', view = '', meta = {} }) {
  const app = LoaderService.importView(view)
  const withHeader = window.$settings.SHOW_HEADER && !meta?.hideHeader
  const withFooter = window.$settings.SHOW_FOOTER && !meta?.hideFooter

  return {
    path,
    name,
    meta: {
      isPublic: false,
      requiresAuth: false,
      withHeader,
      withFooter,
      roles: [],
      ...meta,
    },
    components: {
      default: () => app,
      ...(withHeader ? { header: () => LoaderService.importComponent('AppHeader') } : {}),
      ...(withFooter ? { footer: () => LoaderService.importComponent('AppFooter') } : {}),
    },
  }
}
