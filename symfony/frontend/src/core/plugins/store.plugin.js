import { createPinia } from 'pinia'
import { markRaw } from 'vue'

const StorePlugin = {
  install(app, router) {
    const pinia = createPinia()
    pinia.use(({ store }) => (store.router = markRaw(router)))

    app.use(pinia)
  },
}

export default StorePlugin
