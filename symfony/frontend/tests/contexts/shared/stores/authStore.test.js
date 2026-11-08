import { beforeEach, describe, it } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/contexts/shared/stores/auth.store.js'
import StorageService from '@/core/services/storage.service.js'
import CurrentUserModel from '@/core/models/currentUser.model.js'

describe('[Shared/Stores]', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  describe('AuthStore', () => {
    const currentUser = new CurrentUserModel()
    it('should successfully login', async () => {
      const { login } = useAuthStore()
      await login()
      return currentUser.isAuth
    })
    it('should refresh the token', async () => {
      const { login, refreshToken } = useAuthStore()
      await login({ email: import.meta.env.VITE_TEST_USER, password: import.meta.env.VITE_TEST_PASS })
      const currentToken = `${StorageService.getToken()}`
      await refreshToken({
        headers: {
          'Content-Type': 'application/json',
        },
      })
      return currentToken !== StorageService.getToken()
    })
    it('should successfully logout', async () => {
      const { logout } = useAuthStore()
      await logout()
      return !currentUser.isAuth
    })
  })
})
