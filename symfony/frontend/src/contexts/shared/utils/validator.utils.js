import { ValidationError } from '@/core/models/error.model.js'

function validate(data, schema, abortEarly = false) {
  const { error } = schema.validate(data, { abortEarly })
  if (error) return error.details.map((err) => new ValidationError(err.message, err.context.key))
  return false
}

export function getFirstError(data, schema) {
  return validate(data, schema, true)
}
export function hasErrors(data, schema) {
  return validate(data, schema)
}
