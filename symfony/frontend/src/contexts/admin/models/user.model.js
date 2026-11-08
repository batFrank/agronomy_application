import FormModel from '@/core/models/form.model.js'
import { createUserSchema, updateUserSchema } from '@/contexts/admin/schemas/user.schema.js'
import { cleanText } from '@/core/utils/misc.utils.js'

export default class UserModel extends FormModel {
  constructor(data = {}) {
    super(data || {})
  }

  setContent({ id = '', email = '', roles = [], created_at }) {
    this.id = id || ''
    this.email = email || ''
    this.roles = roles || []
    this.createdAt = created_at || ''
    this.password = ''
    this.password_confirmation = ''
  }

  getPayload() {
    return this.id
      ? {
          email: cleanText(this.email),
          roles: this.roles,
        }
      : {
          email: cleanText(this.email),
          password: cleanText(this.password),
          password_confirmation: cleanText(this.password_confirmation),
          roles: this.roles,
        }
  }

  getValidationSchema() {
    return this.id ? updateUserSchema : createUserSchema
  }
}
