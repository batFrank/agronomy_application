import { ROLES } from '@/core/constants/roles.constants.js'

export const ADMIN_ROUTES = {
  HOME: {
    path: '/admin',
    name: 'admin',
    view: 'AdminView',
    meta: { requiresAuth: true, roles: [ROLES.ADMIN, ROLES.MANAGER], hideFooter: true },
  },
}
