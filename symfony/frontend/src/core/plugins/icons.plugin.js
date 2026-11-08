import FontAwesomeIcon from '@/core/configs/icons.config.js'

const IconsPlugin = {
  install(app) {
    app.component('Icon', FontAwesomeIcon)
  },
}

export default IconsPlugin
