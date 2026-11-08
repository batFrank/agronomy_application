import { validateAccess } from '@/core/utils/misc.utils.js'
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'
import { FooterItemModel } from '@/contexts/layout/models/FooterItem.model.js'

export function buildNavMenu(allPluginMenus, userRoles) {
  const menuMap = {}
  const roles = Array.isArray(userRoles) ? userRoles : [userRoles]

  function hasAccess(requiredRoles) {
    return validateAccess(requiredRoles, roles)
  }

  allPluginMenus.flat().forEach((item) => {
    if (menuMap[item.id]) {
      if (item.subItems.length > 0) {
        menuMap[item.id].subItems = [...menuMap[item.id].subItems, ...item.subItems]
      }
    } else {
      menuMap[item.id] = { ...item }
    }
  })

  return Object.values(menuMap)
    .map((item) => {
      if (item.subItems?.length > 0) {
        item.subItems = item.subItems.filter((child) => hasAccess(child.roles)).sort((a, b) => a.order - b.order)
      }
      return new MenuItemModel(item)
    })
    .filter((item) => {
      if (!hasAccess(item.roles)) return false
      return !(item.isFolder && !item.subItems?.length) || !!item.link
    })
    .sort((a, b) => a.order - b.order)
}

export function buildFooterMenu(allPluginFooters, userRoles) {
  const footerMap = {}
  const roles = Array.isArray(userRoles) ? userRoles : [userRoles]

  function hasAccess(requiredRoles) {
    return validateAccess(requiredRoles, roles)
  }

  allPluginFooters.flat().forEach((item) => {
    if (!footerMap[item.id]) {
      footerMap[item.id] = { ...item }
    }
  })

  return Object.values(footerMap)
    .filter((item) => hasAccess(item.roles))
    .map((item) => new FooterItemModel(item))
    .sort((a, b) => a.order - b.order)
}
