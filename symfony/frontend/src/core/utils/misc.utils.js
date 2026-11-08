import { ROLES } from '../constants/roles.constants.js'

export function objToGetString(params = {}) {
  const paramsFormatted = Object.entries(params).map(([key, value]) => {
    if (value === null || value === undefined) return null
    const param = encodeURIComponent(String(value).trim())
    return `${key}=${param}`
  })

  return `?${paramsFormatted.filter((value) => value).join('&')}`
}

export function stringToJson(data = '', defaultValue = {}) {
  try {
    return JSON.parse(data)
  } catch (e) {
    return defaultValue
  }
}

export function getBrowserLocales() {
  return (navigator.languages || []).map((l) => l.replace(/-\w+/gi, ''))
}

export function deepMerge(target, source) {
  const result = { ...target }
  for (const key of Object.keys(source)) {
    const val = source[key]
    if (val && typeof val === 'object' && !Array.isArray(val)) {
      result[key] = deepMerge(target[key] ?? {}, val)
    } else {
      result[key] = val
    }
  }
  return result
}

export function deepObjectReplace(baseObject, partial) {
  for (let key in partial) {
    if (typeof partial[key] === 'object' && partial[key] !== null && !Array.isArray(partial[key])) {
      if (!baseObject[key]) baseObject[key] = {}
      deepObjectReplace(baseObject[key], partial[key])
    } else {
      baseObject[key] = partial[key]
    }
  }
  return baseObject
}

export function validateAccess(requiredRoles = [], userRoles = []) {
  if (requiredRoles.length === 0) return true
  return userRoles.some((r) => requiredRoles.includes(r) || r === ROLES.ADMIN)
}

export function cleanText(text = '') {
  return String(text).replace(/\s+/g, ' ').trim()
}

export function textToNumber(text = '') {
  return isNaN(+text) ? 0 : +text
}
