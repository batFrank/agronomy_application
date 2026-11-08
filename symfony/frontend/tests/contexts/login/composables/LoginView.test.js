import { beforeEach, describe, it } from 'vitest'
import { useLoginView } from '@/contexts/login/composables/LoginView.composable.js'
import { setActivePinia, createPinia } from 'pinia'
import CurrentUserModel from '@/core/models/currentUser.model.js'

describe('[LoginView/composable]', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  const { login, loginData } = useLoginView()
  describe('LoginForm', () => {
    it('Should throw email error', async () => {
      loginData.payload = { email: '', password: '12345678' }
      await login()
      return 'email' in loginData.errors
    })
    it('Should throw password error', async () => {
      loginData.payload = { email: '', password: '123' }
      await login()
      return 'password' in loginData.errors
    })
    it('Should success login fails', async () => {
      loginData.payload = { email: 'testfail@grupogestionet.com', password: 'password1234' }
      await login()
      const currentUser = new CurrentUserModel()
      return !currentUser.isAuth
    })
    it('Should success login', async () => {
      loginData.payload = { email: import.meta.env.VITE_TEST_USER, password: import.meta.env.VITE_TEST_PASS }
      await login()
      const currentUser = new CurrentUserModel()
      return currentUser.isAuth
    })
  })
})
