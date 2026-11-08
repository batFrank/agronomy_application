import IconsPlugin from '@/core/plugins/icons.plugin.js'
import ToastPlugin from '@/core/plugins/toast.plugin.js'
import DateTimePlugin from '@/core/plugins/datetime.plugin.js'
import { addDirectives } from '@/core/plugins/directives.plugin.js'

/**
 * Everything a component needs to render outside the routed app: global components and
 * directives. Shared by the app entry point and the component catalog so a plugin added
 * for the app cannot be forgotten in the catalog.
 */
export function installUiPlugins(app) {
  IconsPlugin.install(app)
  ToastPlugin.install(app)
  DateTimePlugin.install(app)
  addDirectives(app)
}
