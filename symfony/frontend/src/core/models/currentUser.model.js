import { StorageService } from '@/core/services/storage.service.js'
import { stringToJson } from '@/core/utils/misc.utils.js'
import { ROLES, ROLE_HIERARCHY } from '@/core/constants/roles.constants.js'

export default class CurrentUserModel {
  roles = []
  constructor() {
    this.initData()
  }

  setOnInit(callback = () => {}) {
    this.onInit = callback
  }

  initData() {
    const data = this.getJwtPayloadData()
    this.id = data.id || 0
    this.roles = data.roles || []
    this.username = `${data.username || ''}`.trim()
    this.expire = data.exp || 0
    this.isAuth = !!this.username && (!this.expire || this.expire > Date.now() / 1000)
    if (this.onInit) {
      this.onInit()
    }
  }

  getJwtPayloadData() {
    const [_, payload] = (StorageService.getToken() || '').split('.')
    if (!payload) {
      this.isAuth = false
      return {}
    }

    return stringToJson(window.atob(payload), {})
  }

  // Returns the single highest role the user holds, used as the settings key.
  get role() {
    return ROLE_HIERARCHY.find((r) => this.roles.includes(r)) ?? ROLES.USER
  }

  // Resolves the post-login landing page from settings based on the user's role.
  get authDefault() {
    const defaults = window.$settings.PAGES.AUTH_DEFAULT
    if (typeof defaults === 'string') return defaults
    return defaults[this.role] ?? defaults[ROLES.USER] ?? 'home'
  }

  // Frontend-only guard. Real authorization is enforced by the backend on every request.
  isGranted(actionRoles = []) {
    if (this.roles.includes(ROLES.ADMIN)) return true
    return actionRoles.some((role) => this.roles.includes(role))
  }
}
