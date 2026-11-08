import axios from 'axios'
import { API_URL, PUBLIC_ENDPOINTS } from '@/core/constants/general.constant.js'
import StorageService from '@/core/services/storage.service.js'
import { useAuthStore } from '@/contexts/shared/stores/auth.store.js'
import { ApiError, ConnectionError, ServerError } from '@/core/models/error.model.js'

const instance = axios.create({ baseURL: API_URL })

instance.interceptors.request.use(
  (config) => {
    const token = StorageService.getToken()
    if (token && !PUBLIC_ENDPOINTS.includes(config.url)) config.headers.Authorization = `Bearer ${token}`
    return config
  },
  (error) => Promise.reject(new ApiError(error.response?.data))
)

instance.interceptors.response.use(
  (response) => response,
  async (err) => {
    const { refreshToken, logout } = useAuthStore()
    const originalRequest = err.config

    if (err.response?.status === 401 && !originalRequest.url.includes('/auth/')) {
      if (!originalRequest._retry) {
        originalRequest._retry = true
        await refreshToken({
          ...originalRequest,
          headers: {
            'Content-Type': 'application/json',
          },
        })

        return instance(originalRequest)
      }
      await logout()
    }

    if (err.response?.status === 404) return Promise.reject(new ConnectionError('URL Not Found'))
    if (err.response?.status >= 500) return Promise.reject(new ServerError('Internal error'))

    return Promise.reject(new ApiError(err.response?.data))
  }
)

export default instance
