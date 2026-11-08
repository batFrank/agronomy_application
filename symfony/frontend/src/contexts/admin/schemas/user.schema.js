import Joi from 'joi'
import { ROLES } from '@/core/constants/roles.constants.js'

const validRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.USER]

const rolesField = Joi.array()
  .items(Joi.string().valid(...validRoles))
  .min(1)
  .required()
  .messages({
    'array.min': 'Debe seleccionar al menos un rol',
    'any.required': 'Debe seleccionar al menos un rol',
  })

export const createUserSchema = Joi.object({
  email: Joi.string()
    .email({ tlds: { allow: false } })
    .required()
    .messages({
      'string.email': 'El email no tiene un formato válido',
      'string.empty': 'Debe ingresar un email',
    }),
  password: Joi.string().min(6).required().messages({
    'string.min': 'La contraseña debe tener al menos 6 caracteres',
    'string.empty': 'Debe ingresar una contraseña',
  }),
  password_confirmation: Joi.valid(Joi.ref('password')).required().messages({
    'any.only': 'Las contraseñas no coinciden',
    'any.required': 'Debe confirmar la contraseña',
  }),
  roles: rolesField,
})

export const updateUserSchema = Joi.object({
  email: Joi.string()
    .email({ tlds: { allow: false } })
    .required()
    .messages({
      'string.email': 'El email no tiene un formato válido',
      'string.empty': 'Debe ingresar un email',
    }),
  roles: rolesField,
})
