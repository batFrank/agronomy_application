import CurrentUserModel from '@/core/models/currentUser.model.js'
import { menuReady, menuState } from '@/core/state/menu.state.js'

export async function initValidations(to, router) {
  const currentUser = new CurrentUserModel()
  if (to.name === 'catchAll') {
    if (!menuState.isReady) {
      await menuReady
      return to.fullPath
    }

    if (!currentUser.isAuth) {
      return { name: window.$settings.PAGES.ENTRY_PAGE }
    }
    return { name: currentUser.authDefault }
  }

  if (!window.$settings.AUTH_ACTIVE || to.meta.isPublic) {
    return
  }

  if (!currentUser.isAuth) {
    return to.meta.requiresAuth ? { name: window.$settings.PAGES.ENTRY_PAGE } : undefined
  }

  if (!to.meta.requiresAuth) {
    const authDefault = currentUser.authDefault
    return router.hasRoute(authDefault) ? { name: authDefault } : undefined
  }
}
