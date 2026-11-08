import { reactive, ref } from 'vue'
import FormModel from '@/core/models/form.model.js'

export function useModal() {
  let isOpen = ref(false)
  const data = ref(null)

  function setData(model = {}) {
    data.value = model
  }

  function open(data = {}) {
    setData(data instanceof FormModel ? data.clone() : data)
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
    data.value = null
  }

  return reactive({
    isOpen,
    data,
    open,
    close,
  })
}
