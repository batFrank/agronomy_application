import { reactive, ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { toast } from 'vue3-toastify'
import ApiService from '@/core/services/api.service.js'
import { hasErrors } from '@/contexts/shared/utils/validator.utils.js'
import { passwordResetSchema } from '@/contexts/shared/schemas/auth.schema.js'
import { LoaderService } from '@/core/services/loader.service.js'

export function usePasswordResetView() {
  const router = useRouter()
  const route = useRoute()
  const logo = LoaderService.importImage('images/logo')

  const token = route.params.token
  const stage = ref('reset')

  const resetData = reactive({ payload: { new_password: '' }, errors: {}, loading: false })

  onMounted(async () => {
    const { data, error } = await ApiService.post('/auth/password-check', { reset_token: token })
    if (error || !data?.valid) {
      toast.error('El enlace no es válido o ha expirado')
      stage.value = 'invalid'
    }
  })

  async function reset() {
    if (resetData.loading) return
    resetData.errors = {}
    resetData.loading = true

    const errors = hasErrors(resetData.payload, passwordResetSchema)
    if (errors) {
      errors.forEach((err) => (resetData.errors[err.errorKey] = err.message))
      resetData.loading = false
      return
    }

    const { error } = await ApiService.post('/auth/password-reset', {
      reset_token: token,
      new_password: resetData.payload.new_password,
    })
    resetData.loading = false
    if (error) return toast.error(error.message)

    toast.success('Contraseña actualizada correctamente')
    router.push({ name: 'login' })
  }

  return { logo, stage, resetData, reset }
}
