import { createRouter, createWebHistory } from 'vue-router'
import { initValidations } from '@/core/router/guards.js'

const router = createRouter({
  history: createWebHistory(`/${window.$settings.BASE_DIR}/`),
  routes: [{ path: '/:pathMatch(.*)*', name: 'catchAll', component: { render: () => null } }],
})
router.beforeEach(async (to) => {
  return initValidations(to, router)
})
export default router
