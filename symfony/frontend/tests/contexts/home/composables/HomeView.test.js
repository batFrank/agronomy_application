import { beforeEach, describe, it } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useHomeView } from '@/contexts/home/composables/HomeView.composable.js'
import CurrentUserModel from '@/core/models/currentUser.model.js'

describe('[HomeView/composable]', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  const { logout } = useHomeView()
  it('Should throw email error', async () => {
    await logout()
    const currentUser = new CurrentUserModel()
    return !currentUser.isAuth
  })
})
