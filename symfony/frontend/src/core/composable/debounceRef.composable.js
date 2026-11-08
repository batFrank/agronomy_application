import { ref, watch } from 'vue'

export function useDebouncedRef(callback, delay = 600, initialValue = '') {
  const value = ref(initialValue)
  let timeout

  watch(value, (newVal, oldVal) => {
    clearTimeout(timeout)

    timeout = setTimeout(() => {
      callback(newVal, oldVal)
    }, delay)
  })

  return value
}
