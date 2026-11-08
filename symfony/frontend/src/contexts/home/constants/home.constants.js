import { ROLES } from '@/core/constants/roles.constants.js'

export const HOME_ROUTES = {
  HOME: {
    path: '/home',
    name: 'home',
    view: 'HomeView',
    meta: { requiresAuth: true, roles: Object.values(ROLES) },
  },
}
