export const PASSWORD_RESET_ROUTES = {
  RESET: {
    path: '/reset-password/:token',
    name: 'reset-password',
    view: 'PasswordResetView',
    meta: { isPublic: true },
  },
}
