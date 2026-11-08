import Joi from 'joi'
export default class FormModel {
  constructor(data = {}) {
    this.id = data.id || ''
    this.key = `key_${this.constructor.name.toLowerCase()}${this.id ? `_${this.id}` : ''}`
    this.inProcess = false
    this.originalData = data
    this.setErrors()
    this.setContent(data)
  }

  setContent({} = {}) {}

  getPayload() {}

  getValidationSchema() {
    return Joi.object({})
  }

  getFirstError() {
    const errorList = Object.values(this.errors) || []
    return `${errorList[0]}`
  }

  setErrors(errors = {}) {
    this.errors = errors || {}
  }

  clone() {
    return new this.constructor(this.originalData)
  }
}
