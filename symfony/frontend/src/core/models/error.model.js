export class ConnectionError extends Error {
  constructor(message = '') {
    super(message)
    this.name = 'ConnectionError'
    this.stack = ''
  }
}

export class ServerError extends Error {
  constructor(message = '') {
    super(message)
    this.name = 'ServerError'
    this.stack = ''
  }
}

export class ApiError extends Error {
  constructor(response = {}) {
    const defaultMessage = '418'
    const message = response?.data?.message || response?.message || response?.data || defaultMessage
    super(typeof message === 'string' ? message : defaultMessage)
    this.name = 'ApiError'
    this.stack = ''
  }
}

export class ValidationError extends Error {
  constructor(message, key = '') {
    super(message)
    this.name = 'ValidationError'
    this.errorKey = key
    this.stack = ''
  }
}
