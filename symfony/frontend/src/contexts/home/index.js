import ModuleModel from '@/core/models/module.model.js'
import { HOME_ROUTES } from '@/contexts/home/constants/home.constants.js'
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'
import { FooterItemModel } from '@/contexts/layout/models/FooterItem.model.js'

export default new ModuleModel({
  name: 'Home',
  routes: HOME_ROUTES,
  menuItems: [
    new MenuItemModel({
      id: HOME_ROUTES.HOME.name + '_2',
      title: 'Sample with subitems',
      icon: 'bars',
      order: 3,
      roles: HOME_ROUTES.HOME.meta.roles,
      subItems: [
        new MenuItemModel({ id: 31, title: 'Item 1', link: HOME_ROUTES.HOME.path }),
        new MenuItemModel({ id: 32, title: 'Item External', link: 'https://www.google.com' }),
      ],
    }),
  ],
  footerItems: [
    new FooterItemModel({
      id: HOME_ROUTES.HOME.name,
      title: 'Sample Footer Home',
      link: HOME_ROUTES.HOME.path,
      roles: HOME_ROUTES.HOME.meta.roles,
    }),
  ],
})
