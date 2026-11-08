import ModuleModel from '@/core/models/module.model.js'
import { LOGIN_ROUTES } from '@/contexts/login/constants/login.constants.js'

export default new ModuleModel({
  name: 'Login',
  routes: LOGIN_ROUTES,
})
