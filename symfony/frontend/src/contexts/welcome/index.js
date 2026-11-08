import ModuleModel from '@/core/models/module.model.js'
import { WELCOME_ROUTES } from '@/contexts/welcome/constants/welcome.constants.js'
import { FooterItemModel } from '@/contexts/layout/models/FooterItem.model.js'

export default new ModuleModel({
  name: 'Welcome',
  routes: window.$settings.PAGES?.ENABLED?.WELCOME ? WELCOME_ROUTES : {},
  footerItems: window.$settings.PAGES?.ENABLED?.WELCOME
    ? [
        new FooterItemModel({
          id: WELCOME_ROUTES.HOME.name,
          title: 'Sample Footer Welcome',
          link: WELCOME_ROUTES.HOME.path,
        }),
      ]
    : [],
})
