import Toast, { toast } from 'vue3-toastify'
import 'vue3-toastify/dist/index.css'

const ToastPlugin = {
  install(app) {
    app.use(Toast, {
      autoClose: 3000,
      limit: 3,
      position: toast.POSITION.TOP_RIGHT,
      theme: toast.THEME.COLORED,
    })
  },
}

export default ToastPlugin
