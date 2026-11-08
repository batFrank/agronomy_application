import Joi from 'joi'

export const loginSchema = Joi.object({
  email: Joi.string()
    .email({ tlds: { allow: false } })
    .required()
    .messages({
      'string.email': 'El email no tiene un formato válido',
      'string.empty': 'Debe ingresar un email',
    }),
  password: Joi.string().min(5).required().messages({
    'string.min': 'La contraseña debe contener al menos 5 caracteres',
    'string.empty': 'Debe ingresar una contraseña',
  }),
})

export const emailSchema = Joi.object({
  email: Joi.string()
    .email({ tlds: { allow: false } })
    .required()
    .messages({
      'string.email': 'El email no tiene un formato válido',
      'string.empty': 'Debe ingresar un email',
    }),
})

export const passwordResetSchema = Joi.object({
  new_password: Joi.string().min(6).required().messages({
    'string.min': 'La contraseña debe contener al menos 6 caracteres',
    'string.empty': 'Debe ingresar una contraseña',
  }),
})
