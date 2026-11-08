import ModuleModel from '@/core/models/module.model.js'
import { PASSWORD_RECOVERY_ROUTES } from '@/contexts/passwordRecovery/constants/passwordRecovery.constants.js'

export default new ModuleModel({
  name: 'PasswordRecovery',
  routes: window.$settings.AUTH?.PASSWORD_RECOVERY ? PASSWORD_RECOVERY_ROUTES : {},
})
