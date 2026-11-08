import { useAuthStore } from '@/contexts/shared/stores/auth.store.js'
import { inject, ref } from 'vue'
import { useRouter } from 'vue-router'

export function useHomeView() {
  /** @type CurrentUserModel */
  const currentUser = inject('currentUser')
  const router = useRouter()
  const submitLogout = ref(false)
  async function logout() {
    const { logout } = useAuthStore()
    if (submitLogout.value) return null
    submitLogout.value = true
    await logout()

    if (!currentUser.isAuth) router?.push({ name: window.$settings.PAGES.ENTRY_PAGE }).catch()
    submitLogout.value = false
  }

  return {
    logout,
  }
}
