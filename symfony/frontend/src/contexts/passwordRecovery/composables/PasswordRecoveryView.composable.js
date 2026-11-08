import { reactive, ref } from 'vue'
import { toast } from 'vue3-toastify'
import ApiService from '@/core/services/api.service.js'
import { hasErrors } from '@/contexts/shared/utils/validator.utils.js'
import { emailSchema } from '@/contexts/shared/schemas/auth.schema.js'
import { LoaderService } from '@/core/services/loader.service.js'

export function usePasswordRecoveryView() {
  const logo = LoaderService.importImage('images/logo')
  const stage = ref('recover')

  const recoverData = reactive({ payload: { email: '' }, errors: {}, loading: false })

  async function recover() {
    if (recoverData.loading) return
    recoverData.errors = {}
    recoverData.loading = true

    const errors = hasErrors(recoverData.payload, emailSchema)
    if (errors) {
      errors.forEach((err) => (recoverData.errors[err.errorKey] = err.message))
      recoverData.loading = false
      return
    }

    const { error } = await ApiService.post('/auth/password-recover', recoverData.payload)
    recoverData.loading = false
    if (error) return toast.error(error.message)
    stage.value = 'success'
  }

  return { logo, stage, recoverData, recover }
}
