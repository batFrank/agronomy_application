import { inject, reactive } from 'vue'
import { useAuthStore } from '@/contexts/shared/stores/auth.store.js'
import { hasErrors } from '@/contexts/shared/utils/validator.utils.js'
import { loginSchema } from '@/contexts/shared/schemas/auth.schema.js'
import { LoaderService } from '@/core/services/loader.service.js'
import { useRouter } from 'vue-router'

export function useLoginView() {
  /** @type CurrentUserModel */
  const currentUser = inject('currentUser')
  const router = useRouter()
  const logo = LoaderService.importImage(`images/logo`)
  const loginData = reactive({
    payload: {
      email: '',
      password: '',
    },
    errors: {},
    loading: false,
  })
  async function login() {
    const { login } = useAuthStore()
    if (loginData.loading) return null
    loginData.errors = {}
    loginData.loading = true

    const errors = hasErrors(loginData.payload, loginSchema)
    if (errors) {
      errors.forEach((err) => (loginData.errors[err.errorKey] = err.message))
      loginData.loading = false
      return null
    }

    await login(loginData.payload)
    if (currentUser.isAuth) router?.push({ name: currentUser.authDefault }).catch()
    loginData.loading = false
  }

  return {
    logo,
    login,
    loginData,
  }
}
