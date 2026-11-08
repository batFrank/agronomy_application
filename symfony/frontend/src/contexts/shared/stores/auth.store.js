import { defineStore } from 'pinia'
import { toast } from 'vue3-toastify'
import { REFRESH_TOKEN_URL } from '@/core/constants/general.constant.js'
import StorageService from '@/core/services/storage.service.js'
import ApiService from '@/core/services/api.service.js'
import { inject } from 'vue'

export const useAuthStore = defineStore('authStore', () => {
  /** @type CurrentUserModel */
  const currentUser = inject('currentUser')
  async function login(payload = {}) {
    const { data, error } = await ApiService.post('/auth/login', payload)
    if (error || !data?.token) return toast.error(`${error?.message || 'Mising token'}`)
    StorageService.setToken(data.token)
    StorageService.setRefreshToken(data.refresh_token)
    currentUser.initData()
  }
  async function refreshToken(config = {}) {
    const storedRefreshToken = StorageService.getRefreshToken()
    if (!storedRefreshToken) return undefined
    const { data, error } = await ApiService.post(REFRESH_TOKEN_URL, { refresh_token: storedRefreshToken }, config)
    if (error || !data?.refresh_token || !data?.token) return toast.error(`${error?.message || 'Mising token'}`)
    StorageService.setToken(data.token)
    StorageService.setRefreshToken(data.refresh_token)
    currentUser.initData()
  }
  async function logout() {
    const storedRefreshToken = StorageService.getRefreshToken()
    StorageService.setToken()
    StorageService.setRefreshToken()
    currentUser.initData()
    const { error } = await ApiService.post('/auth/logout', { refresh_token: storedRefreshToken })
    if (error) toast.error(error.message)
  }

  return {
    login,
    logout,
    refreshToken,
  }
})
