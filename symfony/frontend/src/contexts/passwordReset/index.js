import ModuleModel from '@/core/models/module.model.js'
import { PASSWORD_RESET_ROUTES } from '@/contexts/passwordReset/constants/passwordReset.constants.js'

export default new ModuleModel({
  name: 'PasswordReset',
  routes: window.$settings.AUTH?.PASSWORD_RECOVERY ? PASSWORD_RESET_ROUTES : {},
})
