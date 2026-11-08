import { onBeforeUnmount, onMounted, ref } from 'vue'

const subscribers = new Set()
let initialized = false

function notifyAll() {
  const data = {
    width: window.innerWidth,
    height: window.innerHeight,
  }
  subscribers.forEach((cb) => cb(data))
}

export function useViewportComposable() {
  const data = ref({
    width: window.innerWidth,
    height: window.innerHeight,
  })

  function update(newVal) {
    data.value = newVal
  }

  onMounted(() => {
    subscribers.add(update)

    if (!initialized) {
      window.addEventListener('resize', notifyAll, { passive: true })
      initialized = true
    }

    notifyAll()
  })

  onBeforeUnmount(() => {
    subscribers.delete(update)

    if (subscribers.size === 0) {
      window.removeEventListener('resize', notifyAll)
      initialized = false
    }
  })

  return data
}
