import axiosInstance from '@/core/configs/axios.config.js'
import { ApiError, ConnectionError, ServerError } from '@/core/models/error.model.js'
import { objToGetString } from '@/core/utils/misc.utils.js'

class ApiService {
  static async axiosCall(config = {}) {
    return await axiosInstance
      .request(config)
      .then((response) => ({ error: false, ...response.data }))
      .catch((error) => {
        if (error instanceof ApiError) return { error }
        if (error instanceof ConnectionError) return { error }
        if (error instanceof ServerError) return { error }
        return { error: new ConnectionError('Connection error') }
      })
  }

  static async get(url = '', data = {}, config = {}) {
    let urlParsed = Object.keys(data).length ? `${url}${objToGetString(data)}` : url
    return this.axiosCall({ ...config, url: urlParsed, method: 'GET' })
  }

  static async post(url = '', data = {}, config = {}) {
    return this.axiosCall({ ...config, url, method: 'POST', data })
  }

  static async put(url = '', data = {}, config = {}) {
    return this.axiosCall({ ...config, url, method: 'PUT', data })
  }

  static async patch(url = '', data = {}, config = {}) {
    return this.axiosCall({ ...config, url, method: 'PATCH', data })
  }

  static async delete(url = '', config = {}) {
    return this.axiosCall({ ...config, url, method: 'DELETE' })
  }
}

export default ApiService
