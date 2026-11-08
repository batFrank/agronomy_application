import { reactive, shallowRef } from 'vue'
import TabModel from '@/core/models/tab.model.js'

export default function useTabs(tabsObj = {}, defaultTab = new TabModel(), onChange = () => {}) {
  /** @type {TabsConfig} */
  const configTabs = reactive({
    tabs: Object.values(tabsObj),
    current: shallowRef(defaultTab),
    setTab: (tab) => {
      if (tab instanceof TabModel) {
        configTabs.current = tab
        onChange()
      }
    },
  })

  return {
    configTabs,
  }
}
