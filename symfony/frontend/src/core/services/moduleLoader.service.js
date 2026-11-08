import { nextTick, ref } from 'vue'
import { MODULE_REGISTRY } from '@/contexts/registry.js'
import { validateAccess } from '@/core/utils/misc.utils.js'
import { buildFooterMenu, buildNavMenu } from '@/core/services/menuLoader.services.js'
import { routeFactory } from '@/core/router/routes.js'
import CurrentUserModel from '@/core/models/currentUser.model.js'

export async function buildMenu(app, router, currentUser = new CurrentUserModel(), onReady = () => {}) {
  const menuItems = ref([])
  const footerItems = ref([])

  router
    .getRoutes()
    .filter((route) => route.name !== 'catchAll')
    .forEach((route) => router.removeRoute(route.name))

  for (const plugin of Object.values(MODULE_REGISTRY)) {
    try {
      if (plugin.routes) {
        Object.values(plugin.routes).forEach((route) => {
          const requiredRoles = route.meta?.roles || []
          if (validateAccess(requiredRoles, currentUser.roles)) {
            router.addRoute(routeFactory(route))
          }
        })
      }

      if (typeof plugin.install === 'function') {
        plugin.install(app)
      }

      menuItems.value.push(plugin.menuItems)
      footerItems.value.push(plugin.footerItems)
    } catch (e) {
      console.error(`Error cargando módulo [${plugin.name ?? 'desconocido'}]:`, e)
    }
  }
  await nextTick()
  onReady()
  return {
    menu: buildNavMenu(menuItems.value, currentUser.roles),
    footer: buildFooterMenu(footerItems.value, currentUser.roles),
  }
}
