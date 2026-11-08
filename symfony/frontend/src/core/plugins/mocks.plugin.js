import { createServer } from 'miragejs'
import { mocksRoutes } from '@/core/configs/mocks.config.js'
import { API_URL } from '@/core/constants/general.constant.js'

const MockServer = {
  install() {
    const routeList = Object.values(mocksRoutes)
      .map((route) => route.default)
      .flat()

    createServer({
      routes() {
        routeList.forEach((route) => {
          const { method, url, callback } = route
          this[method || 'get'](`${API_URL}${url}`, callback)
        })
      },
    })
  },
}

export default MockServer
