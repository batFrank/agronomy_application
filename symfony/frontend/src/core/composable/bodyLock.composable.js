import { onMounted, onUnmounted } from 'vue'

export default function useModalObserver(className = 'BaseModal') {
  const checkModals = () => {
    if (typeof document !== 'undefined') {
      const hasModals = document.querySelector(`.${className}`) !== null
      document.body.style.overflowY = hasModals ? 'hidden' : ''
    }
  }

  const observer = new MutationObserver(() => {
    checkModals()
  })

  onMounted(() => {
    checkModals()
    observer.observe(document.body, {
      childList: true,
      subtree: true,
    })
  })

  onUnmounted(() => {
    observer.disconnect()
    if (typeof document !== 'undefined') {
      document.body.style.overflowY = ''
    }
  })

  return {
    checkModals,
  }
}
