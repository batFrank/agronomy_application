import ModuleModel from '@/core/models/module.model.js'
import { ADMIN_ROUTES } from '@/contexts/admin/constants/admin.constants.js'
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'
import { FooterItemModel } from '@/contexts/layout/models/FooterItem.model.js'

export default new ModuleModel({
  name: 'Admin',
  routes: ADMIN_ROUTES,
  menuItems: [
    new MenuItemModel({
      id: ADMIN_ROUTES.HOME.name,
      title: 'Sample Header',
      link: ADMIN_ROUTES.HOME.path,
      roles: ADMIN_ROUTES.HOME.meta.roles,
      order: 2,
    }),
  ],
  footerItems: [
    new FooterItemModel({
      id: ADMIN_ROUTES.HOME.name,
      title: 'Sample Footer Admin',
      link: ADMIN_ROUTES.HOME.path,
      roles: ADMIN_ROUTES.HOME.meta.roles,
    }),
  ],
})
