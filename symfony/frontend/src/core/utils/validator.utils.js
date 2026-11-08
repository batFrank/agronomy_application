import { ValidationError } from '@/core/models/error.model.js'
import FormModel from '@/core/models/form.model.js'

function validate(data, schema, abortEarly = false) {
  const { error } = schema.validate(data, { abortEarly })
  if (error) {
    return error.details.map((err) => new ValidationError(err.message, err.path.join('.')))
  }
  return false
}

export function validateForm(model = new FormModel(), abortEarly = false) {
  model.setErrors()
  const errors = validate(model.getPayload(), model.getValidationSchema(), abortEarly)
  if (errors.length) model.setErrors(errors.reduce((acc, cur) => ({ ...acc, [cur.errorKey]: cur.message }), {}))
  return !!errors.length
}
