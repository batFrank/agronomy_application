import router from '@/core/router/index'

const RouterPlugin = {
  install(app) {
    app.use(router)
    return router
  },
}

export default RouterPlugin
