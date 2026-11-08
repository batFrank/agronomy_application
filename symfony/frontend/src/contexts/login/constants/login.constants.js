export const LOGIN_ROUTES = {
  HOME: {
    path: '/login',
    name: 'login',
    view: 'LoginView',
    meta: {
      hideFooter: true,
      hideHeader: true,
      requiresAuth: false,
    },
  },
}
