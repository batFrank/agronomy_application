import { computed, reactive, ref } from 'vue'

export default function useFilters({ filters = {}, onReset = function () {} } = {}) {
  const isOpen = ref(false)
  const available = ref(filters)

  function open() {
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
  }

  function toggle() {
    isOpen.value = !isOpen.value
  }

  function getFilters() {
    return Object.entries(available.value)
      .map(([key, value]) => ({ key, value }))
      .filter((filter) => typeof filter.key === 'boolean' || !!filter.value)
      .reduce((acc, cur) => ({ ...acc, [cur.key]: cur.value }), {})
  }

  function reset() {
    Object.keys(available.value).forEach((key) => (available.value[key] = ''))
    if (typeof onReset === 'function') {
      onReset()
    }
  }

  const icon = computed(() => (isOpen.value ? 'FilterOff' : 'Filter'))

  return reactive({
    isOpen,
    icon,
    available,
    open,
    close,
    toggle,
    getFilters,
    reset,
  })
}
