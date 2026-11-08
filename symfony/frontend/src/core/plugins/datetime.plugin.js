import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

const vueDatePicker = {
  install(app) {
    app.component('DateTimePicker', VueDatePicker)
  },
}

export default vueDatePicker
